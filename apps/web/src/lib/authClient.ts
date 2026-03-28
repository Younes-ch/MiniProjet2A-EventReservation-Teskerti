export type AuthUser = {
  email: string;
  display_name: string;
  roles: string[];
};

export type AuthLoginResponse = {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
  user: AuthUser;
};

export type PasskeyAllowedCredential = {
  id: string;
  type: "public-key";
};

export type PasskeyOptionsResponse = {
  challenge: string;
  timeout: number;
  rp_id: string;
  user_verification: string;
  allow_credentials: PasskeyAllowedCredential[];
};

export type PasskeyVerifyPayload = {
  email: string;
  challenge: string;
  credential_id: string;
};

export type PasskeyRegistrationOptionsResponse = {
  challenge: string;
  timeout: number;
  rp_id: string;
  user: {
    email: string;
    display_name: string;
  };
  exclude_credentials: PasskeyAllowedCredential[];
  label: string;
};

export type PasskeyRegistrationVerifyPayload = {
  challenge: string;
  credential_id: string;
  label?: string;
};

export type PasskeyRegistrationVerifyResponse = {
  status: string;
  credential: {
    id: string;
    type: string;
    label: string;
  };
  total_credentials: number;
};

type LogoutResponse = {
  status: string;
};

type LoginPayload = {
  email: string;
  password: string;
};

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? "").replace(/\/$/, "");

const buildApiUrl = (path: string) => `${API_BASE_URL}${path}`;

const extractErrorMessage = (data: unknown): string | null => {
  if (!data || typeof data !== "object") {
    return null;
  }

  const candidate = (data as { error?: unknown }).error;
  if (typeof candidate === "string" && candidate.trim().length > 0) {
    return candidate;
  }

  return null;
};

const requestJson = async <T>(path: string, init?: RequestInit): Promise<T> => {
  const response = await fetch(buildApiUrl(path), {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(init?.headers ?? {}),
    },
  });

  const payload = await response
    .json()
    .catch(() => ({} as Record<string, unknown>));

  if (!response.ok) {
    const message = extractErrorMessage(payload) ?? "request_failed";
    throw new Error(message);
  }

  return payload as T;
};

export const loginWithPassword = (payload: LoginPayload) =>
  requestJson<AuthLoginResponse>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify(payload),
  });

export const refreshAccessToken = (refreshToken: string) =>
  requestJson<AuthLoginResponse>("/api/auth/refresh", {
    method: "POST",
    body: JSON.stringify({
      refresh_token: refreshToken,
    }),
  });

export const fetchAuthenticatedUser = (accessToken: string) =>
  requestJson<AuthUser>("/api/auth/me", {
    method: "GET",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });

export const logoutWithRefreshToken = (refreshToken: string) =>
  requestJson<LogoutResponse>("/api/auth/logout", {
    method: "POST",
    body: JSON.stringify({
      refresh_token: refreshToken,
    }),
  });

export const fetchPasskeyOptions = (email: string) =>
  requestJson<PasskeyOptionsResponse>("/api/auth/passkey/options", {
    method: "POST",
    body: JSON.stringify({
      email,
    }),
  });

export const verifyPasskeyLogin = (payload: PasskeyVerifyPayload) =>
  requestJson<AuthLoginResponse>("/api/auth/passkey/verify", {
    method: "POST",
    body: JSON.stringify(payload),
  });

export const fetchPasskeyRegistrationOptions = (
  accessToken: string,
  label?: string,
) =>
  requestJson<PasskeyRegistrationOptionsResponse>(
    "/api/auth/passkey/register/options",
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${accessToken}`,
      },
      body: JSON.stringify({
        label,
      }),
    },
  );

export const verifyPasskeyRegistration = (
  accessToken: string,
  payload: PasskeyRegistrationVerifyPayload,
) =>
  requestJson<PasskeyRegistrationVerifyResponse>(
    "/api/auth/passkey/register/verify",
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${accessToken}`,
      },
      body: JSON.stringify(payload),
    },
  );
