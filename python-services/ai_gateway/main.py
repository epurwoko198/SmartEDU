"""EDUSMART ERP - AI Gateway (Phase 1 scaffold).

The PHP application calls this service with the authenticated user's tenant,
role and permission context. The gateway:

* requires service-to-service bearer authentication,
* enforces a per-tenant daily quota (in-memory placeholder; move to the core DB),
* applies basic prompt-injection and transactional-action guardrails,
* routes to a mock provider or any OpenAI-compatible endpoint,
* never logs message content.

The AI is never the authority for grades, graduation, billing or data changes:
requests that look transactional are returned with ``requires_approval=True``.
"""

from __future__ import annotations

import hmac
import logging
import os
import re
import uuid
from datetime import date
from typing import Literal

import httpx
from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

APP_VERSION = "0.1.0"

GATEWAY_TOKEN = os.getenv("AI_GATEWAY_TOKEN", "").strip()
PROVIDER = os.getenv("AI_PROVIDER", "mock").strip().lower()
BASE_URL = os.getenv("AI_BASE_URL", "").strip().rstrip("/")
API_KEY = os.getenv("AI_API_KEY", "").strip()
MODEL = os.getenv("AI_MODEL", "gpt-4o-mini").strip()
DAILY_QUOTA = int(os.getenv("AI_DAILY_QUOTA", "200"))
MAX_MESSAGES = int(os.getenv("AI_MAX_MESSAGES", "12"))
REQUEST_TIMEOUT = float(os.getenv("AI_TIMEOUT_SECONDS", "45"))

logging.basicConfig(
    level=os.getenv("LOG_LEVEL", "INFO"),
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
)
logger = logging.getLogger("edusmart.ai")

app = FastAPI(
    title="EDUSMART AI Gateway",
    version=APP_VERSION,
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)

# ---------------------------------------------------------------------------
# Contract
# ---------------------------------------------------------------------------


class UserContext(BaseModel):
    id: int
    role: str = Field(min_length=1, max_length=60)
    permissions: list[str] = Field(default_factory=list, max_length=100)


class ChatMessage(BaseModel):
    role: Literal["system", "user", "assistant"]
    content: str = Field(min_length=1, max_length=8000)


class ChatRequest(BaseModel):
    tenant_id: int = Field(gt=0)
    tenant_slug: str = Field(min_length=1, max_length=63)
    user: UserContext
    feature: str = Field(default="assistant", max_length=60)
    messages: list[ChatMessage] = Field(min_length=1, max_length=MAX_MESSAGES)
    context: list[str] = Field(default_factory=list, max_length=20)


class ChatResponse(BaseModel):
    request_id: str
    answer: str
    provider: str
    model: str
    tenant_id: int
    requires_approval: bool
    flagged: bool
    quota_used: int
    quota_limit: int


# ---------------------------------------------------------------------------
# Guardrails and quota
# ---------------------------------------------------------------------------

INJECTION_PATTERNS = [
    r"ignore (all )?(previous|prior|above) instructions",
    r"abaikan (semua )?(instruksi|perintah) (sebelumnya|di atas)",
    r"(reveal|show|print).{0,20}(system prompt|api key|token|secret)",
    r"tampilkan.{0,20}(prompt sistem|api key|token)",
    r"drop\s+table",
    r"delete\s+from",
]

TRANSACTIONAL_PATTERNS = [
    r"\b(hapus|ubah|ganti|bayar|transfer|terbitkan|kirim|setujui|batalkan)\b.{0,40}\b(data|nilai|tagihan|sertifikat|surat|siswa|rapor|pembayaran)\b",
    r"\b(delete|update|pay|issue|approve|send)\b.{0,40}\b(data|grade|invoice|letter|certificate|payment)\b",
]

_quota: dict[str, dict[str, int]] = {}


def inspect_input(text: str) -> tuple[bool, bool]:
    """Return (flagged_injection, requires_approval)."""

    lowered = text.lower()
    flagged = any(re.search(pattern, lowered) for pattern in INJECTION_PATTERNS)
    transactional = any(re.search(pattern, lowered) for pattern in TRANSACTIONAL_PATTERNS)
    return flagged, transactional


def enforce_quota(tenant_id: int) -> int:
    if DAILY_QUOTA <= 0:
        return 0

    today = date.today().isoformat()
    if today not in _quota:
        _quota.clear()
        _quota[today] = {}

    bucket = _quota[today]
    key = str(tenant_id)
    used = bucket.get(key, 0)

    if used >= DAILY_QUOTA:
        raise HTTPException(status_code=429, detail="Kuota AI harian untuk sekolah ini habis.")

    bucket[key] = used + 1
    return used + 1


def require_service_token(authorization: str | None = Header(default=None)) -> None:
    if not GATEWAY_TOKEN:
        raise HTTPException(
            status_code=503,
            detail="AI_GATEWAY_TOKEN belum dikonfigurasi pada layanan gateway.",
        )

    expected = f"Bearer {GATEWAY_TOKEN}"
    if authorization is None or not hmac.compare_digest(authorization, expected):
        raise HTTPException(status_code=401, detail="Token layanan tidak valid.")


# ---------------------------------------------------------------------------
# Providers
# ---------------------------------------------------------------------------


def build_system_prompt(request: ChatRequest) -> str:
    permissions = ", ".join(request.user.permissions[:40]) or "-"
    return (
        "Anda adalah Asisten AI EDUSMART ERP untuk administrasi sekolah. "
        "Jawab dalam bahasa Indonesia yang ringkas dan profesional.\n"
        f"Konteks tenant: {request.tenant_slug} (id {request.tenant_id}).\n"
        f"Peran pengguna: {request.user.role}. Permission: {permissions}.\n"
        "Aturan: gunakan hanya data tenant ini; jangan mengarang angka atau data siswa; "
        "jangan mencampur informasi dari sekolah lain; jangan mengeksekusi perubahan data, "
        "pembayaran, penerbitan sertifikat, atau keputusan kelulusan. "
        "Untuk tindakan tersebut, sarankan pengguna memakai menu aplikasi dan minta persetujuan petugas berwenang."
    )


class MockProvider:
    name = "mock"

    async def chat(self, request: ChatRequest) -> tuple[str, dict]:
        last_user_message = next(
            (message.content for message in reversed(request.messages) if message.role == "user"),
            "",
        )
        context_note = (
            f" {len(request.context)} potongan konteks tenant tersedia."
            if request.context
            else " Belum ada konteks dokumen yang dilampirkan."
        )
        answer = (
            "[mock] Gateway AI aktif untuk "
            f"{request.tenant_slug}. Permintaan terakhir: \"{last_user_message[:180]}\"."
            f"{context_note} Integrasi provider cloud/lokal dan RAG tenant-aware "
            "diaktifkan pada Fase 6; jawaban ini hanya verifikasi kontrak layanan."
        )
        return answer, {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0}


class OpenAICompatibleProvider:
    name = "openai_compatible"

    async def chat(self, request: ChatRequest) -> tuple[str, dict]:
        if not BASE_URL:
            raise HTTPException(status_code=503, detail="AI_BASE_URL belum dikonfigurasi.")

        messages = [{"role": "system", "content": build_system_prompt(request)}]
        if request.context:
            context_text = "\n\n".join(request.context[:20])
            messages.append(
                {
                    "role": "system",
                    "content": "Konteks dokumen tenant (hanya gunakan bila relevan):\n" + context_text[:12000],
                }
            )
        messages.extend(
            {"role": message.role, "content": message.content}
            for message in request.messages
            if message.role != "system"
        )

        headers = {"Content-Type": "application/json"}
        if API_KEY:
            headers["Authorization"] = f"Bearer {API_KEY}"

        payload = {
            "model": MODEL,
            "messages": messages,
            "temperature": 0.2,
            "max_tokens": 800,
        }

        try:
            async with httpx.AsyncClient(timeout=REQUEST_TIMEOUT) as client:
                response = await client.post(f"{BASE_URL}/chat/completions", json=payload, headers=headers)
                response.raise_for_status()
                data = response.json()
        except httpx.HTTPError as error:
            logger.warning("provider_error tenant=%s error=%s", request.tenant_id, type(error).__name__)
            raise HTTPException(status_code=502, detail="Provider AI tidak dapat dihubungi.") from error

        try:
            answer = data["choices"][0]["message"]["content"]
        except (KeyError, IndexError, TypeError) as error:
            raise HTTPException(status_code=502, detail="Respons provider AI tidak sesuai kontrak.") from error

        usage = data.get("usage") or {}
        return str(answer), {
            "prompt_tokens": int(usage.get("prompt_tokens", 0)),
            "completion_tokens": int(usage.get("completion_tokens", 0)),
            "total_tokens": int(usage.get("total_tokens", 0)),
        }


def provider() -> MockProvider | OpenAICompatibleProvider:
    if PROVIDER in {"openai", "openai_compatible", "cloud"}:
        return OpenAICompatibleProvider()

    return MockProvider()


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------


@app.get("/")
async def index() -> dict:
    return {
        "service": "edusmart-ai-gateway",
        "version": APP_VERSION,
        "provider": provider().name,
        "endpoints": ["/health", "/v1/chat"],
    }


@app.get("/health")
async def health() -> dict:
    return {
        "status": "ok",
        "service": "edusmart-ai-gateway",
        "version": APP_VERSION,
        "provider": provider().name,
        "model": MODEL,
        "token_configured": bool(GATEWAY_TOKEN),
        "daily_quota": DAILY_QUOTA,
    }


@app.post("/v1/chat", response_model=ChatResponse, dependencies=[Depends(require_service_token)])
async def chat(request: ChatRequest) -> ChatResponse:
    request_id = uuid.uuid4().hex
    quota_used = enforce_quota(request.tenant_id)

    combined = "\n".join(message.content for message in request.messages)
    flagged, requires_approval = inspect_input(combined)

    logger.info(
        "chat request_id=%s tenant=%s user=%s feature=%s flagged=%s approval=%s",
        request_id,
        request.tenant_id,
        request.user.id,
        request.feature,
        flagged,
        requires_approval,
    )

    if flagged:
        return ChatResponse(
            request_id=request_id,
            answer=(
                "Permintaan ini terindikasi mencoba mengubah aturan sistem atau meminta data sensitif, "
                "sehingga tidak diproses. Silakan ajukan pertanyaan administratif yang spesifik."
            ),
            provider=provider().name,
            model=MODEL,
            tenant_id=request.tenant_id,
            requires_approval=False,
            flagged=True,
            quota_used=quota_used,
            quota_limit=DAILY_QUOTA,
        )

    answer, _usage = await provider().chat(request)

    if requires_approval:
        answer = (
            answer
            + "\n\nCatatan: tindakan yang mengubah data atau menerbitkan dokumen memerlukan "
            "persetujuan petugas berwenang dan tidak dijalankan oleh AI."
        )

    return ChatResponse(
        request_id=request_id,
        answer=answer,
        provider=provider().name,
        model=MODEL,
        tenant_id=request.tenant_id,
        requires_approval=requires_approval,
        flagged=False,
        quota_used=quota_used,
        quota_limit=DAILY_QUOTA,
    )
