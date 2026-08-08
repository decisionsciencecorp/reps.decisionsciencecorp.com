"""Exceptions for the Reps dashboard API client."""

from __future__ import annotations

from typing import Any, Optional


class RepsError(Exception):
    """Base exception for reps_sdk."""


class APIError(RepsError):
    def __init__(
        self,
        message: str,
        status_code: Optional[int] = None,
        response: Optional[dict[str, Any]] = None,
    ):
        super().__init__(message)
        self.status_code = status_code
        self.response = response


class AuthenticationError(APIError):
    """Invalid or missing API key / session."""


class NotFoundError(APIError):
    """Resource not found or out of scope."""


class ValidationError(APIError):
    """Bad request / method not allowed."""


class ForbiddenError(APIError):
    """Authenticated but not permitted."""
