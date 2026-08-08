"""Reps dashboard API client — `/dashboard/api/*.php` + Partner proxy."""

from __future__ import annotations

import os
from typing import Any, Dict, Optional

import requests

from reps_sdk.exceptions import (
    APIError,
    AuthenticationError,
    ForbiddenError,
    NotFoundError,
    ValidationError,
)

DEFAULT_BASE = "https://reps.decisionsciencecorp.com"


class RepsClient:
    """
    Thin client over the Reps dashboard JSON API.

    Auth: ``X-API-Key`` (preferred for agents) or pass a cookie jar via
    ``session`` if you already hold a browser login.
    """

    def __init__(
        self,
        api_key: Optional[str] = None,
        base_url: Optional[str] = None,
        timeout: float = 60.0,
    ):
        self.base_url = (
            base_url
            or os.environ.get("REPS_API_BASE_URL")
            or os.environ.get("REPS_DASH_BASE_URL")
            or DEFAULT_BASE
        ).rstrip("/")
        self.api_key = (
            api_key
            or os.environ.get("REPS_SMCP_API_KEY")
            or os.environ.get("REPS_API_KEY")
            or os.environ.get("REPS_DSC_AGENT_API_KEY")
            or ""
        ).strip()
        self.timeout = timeout
        self.api_base = f"{self.base_url}/dashboard/api"
        self.session = requests.Session()
        self.session.headers.update(
            {
                "Accept": "application/json",
                "Content-Type": "application/json",
            }
        )
        if self.api_key:
            self.session.headers["X-API-Key"] = self.api_key

    def _request(
        self,
        method: str,
        endpoint: str,
        *,
        params: Optional[Dict[str, Any]] = None,
        data: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        url = f"{self.api_base}/{endpoint.lstrip('/')}"
        try:
            response = self.session.request(
                method=method,
                url=url,
                params=params,
                json=data,
                timeout=self.timeout,
            )
        except requests.exceptions.RequestException as exc:
            raise APIError(f"Request failed: {exc}") from exc

        try:
            body = response.json() if response.content else {}
        except ValueError as exc:
            raise APIError(f"Invalid JSON: {response.text[:200]}") from exc
        if not isinstance(body, dict):
            body = {"data": body}

        err = body.get("error") or body.get("message") or f"HTTP {response.status_code}"
        if response.status_code == 401:
            raise AuthenticationError(str(err), 401, body)
        if response.status_code == 403:
            raise ForbiddenError(str(err), 403, body)
        if response.status_code == 404:
            raise NotFoundError(str(err), 404, body)
        if response.status_code in (400, 405, 422):
            raise ValidationError(str(err), response.status_code, body)
        if response.status_code >= 400 or body.get("ok") is False:
            raise APIError(str(err), response.status_code, body)
        return body

    # --- book ---
    def health(self) -> Dict[str, Any]:
        return self._request("GET", "health.php")

    def me(self) -> Dict[str, Any]:
        return self._request("GET", "me.php")

    def list_shops(self, **params: Any) -> Dict[str, Any]:
        return self._request("GET", "list-shops.php", params=params or None)

    def get_shop(self, shop_id: int) -> Dict[str, Any]:
        return self._request("GET", "get-shop.php", params={"id": int(shop_id)})

    def list_operators(self, **params: Any) -> Dict[str, Any]:
        return self._request("GET", "list-operators.php", params=params or None)

    def get_operator(self, operator_id: int) -> Dict[str, Any]:
        return self._request("GET", "get-operator.php", params={"id": int(operator_id)})

    def list_sessions(self, **params: Any) -> Dict[str, Any]:
        return self._request("GET", "list-sessions.php", params=params or None)

    def get_session(self, session_id: int) -> Dict[str, Any]:
        return self._request("GET", "get-session.php", params={"id": int(session_id)})

    def money_summary(self) -> Dict[str, Any]:
        return self._request("GET", "money-summary.php")

    def list_api_keys(self, user_id: Optional[int] = None, include_revoked: bool = False) -> Dict[str, Any]:
        params: Dict[str, Any] = {}
        if user_id is not None:
            params["user_id"] = int(user_id)
        if include_revoked:
            params["include_revoked"] = "1"
        return self._request("GET", "list-api-keys.php", params=params or None)

    def create_api_key(self, user_id: int, name: str = "default") -> Dict[str, Any]:
        return self._request(
            "POST",
            "create-api-key.php",
            data={"user_id": int(user_id), "name": name},
        )

    def revoke_api_key(self, key_id: int) -> Dict[str, Any]:
        return self._request("POST", "revoke-api-key.php", data={"key_id": int(key_id)})

    # --- Partner proxy (admin / ops / agent) ---
    def partner_sync(self) -> Dict[str, Any]:
        return self._request("POST", "v1/shift/sync.php")

    def partner_hours_feed(self, ingest: bool = False) -> Dict[str, Any]:
        params = {"ingest": "1"} if ingest else None
        return self._request("GET", "v1/shift/hours-feed.php", params=params)

    def partner_workers(self) -> Dict[str, Any]:
        return self._request("GET", "v1/shift/workers.php")

    def partner_team_members(self) -> Dict[str, Any]:
        return self._request("GET", "v1/shift/team-members.php")

    def partner_invite_team_member(self, name: str, phone: str) -> Dict[str, Any]:
        return self._request(
            "POST",
            "v1/shift/team-members.php",
            data={"name": name, "phone": phone},
        )

    def partner_derived_worker(self, operator_id: int) -> Dict[str, Any]:
        return self._request(
            "GET",
            "v1/shift/derived/worker.php",
            params={"id": int(operator_id)},
        )

    def partner_derived_day(self, date: str) -> Dict[str, Any]:
        return self._request(
            "GET",
            "v1/shift/derived/day.php",
            params={"date": date},
        )

    def partner_derived_issues(self) -> Dict[str, Any]:
        return self._request("GET", "v1/shift/derived/issues.php")
