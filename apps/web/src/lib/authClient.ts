export type AuthUser = {
  email: string;
  display_name: string;
  roles: string[];
};

export type AuthTokenResponse = {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
  auth_method: string;
  passkey_verified: boolean;
  user: AuthUser;
};

export type PasswordLoginResponse =
  | AuthTokenResponse
  | {
      requires_passkey: true;
      user: AuthUser;
      passkey_options: PasskeyOptionsResponse;
    };

export type AuthProfile = AuthUser & {
  auth_method: string;
  passkey_verified: boolean;
  passkey_required_after_password_login: boolean;
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
  client_data: PasskeyClientData;
};

export type PasskeyClientData = {
  type: "webauthn.get" | "webauthn.create";
  challenge: string;
  origin: string;
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
  client_data: PasskeyClientData;
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

export type PasskeyCredentialItem = {
  id: string;
  type: string;
  label: string;
};

export type PasskeyCredentialsResponse = {
  items: PasskeyCredentialItem[];
};

export type PasskeyPolicyResponse = {
  require_passkey_after_password_login: boolean;
};

type LogoutResponse = {
  status: string;
};

type LoginPayload = {
  email: string;
  password: string;
};

export type SignupPayload = {
  email: string;
  display_name: string;
  password: string;
};

export type SignupResponse = {
  status: "user_created";
  user: AuthUser;
};

export type ForgotPasswordResponse = {
  status: "reset_instructions_sent";
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
  const requestHeaders = new Headers(init?.headers ?? {});
  if (init?.body !== undefined && init.body !== null && !requestHeaders.has("Content-Type")) {
    requestHeaders.set("Content-Type", "application/json");
  }

  const response = await fetch(buildApiUrl(path), {
    ...init,
    headers: requestHeaders,
  });

  if (response.status === 204) {
    return {} as T;
  }

  let payload: unknown;

  try {
    payload = await response.json();
  } catch {
    if (!response.ok) {
      throw new Error("request_failed");
    }

    throw new Error("invalid_json_response");
  }

  if (!response.ok) {
    const message = extractErrorMessage(payload) ?? "request_failed";
    throw new Error(message);
  }

  return payload as T;
};

export const loginWithPassword = (payload: LoginPayload) =>
  requestJson<PasswordLoginResponse>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify(payload),
  });

export const signupWithPassword = (payload: SignupPayload) =>
  requestJson<SignupResponse>("/api/auth/signup", {
    method: "POST",
    body: JSON.stringify(payload),
  });

export const requestPasswordReset = (email: string) =>
  requestJson<ForgotPasswordResponse>("/api/auth/forgot-password", {
    method: "POST",
    body: JSON.stringify({ email }),
  });

export const refreshAccessToken = (refreshToken: string) =>
  requestJson<AuthTokenResponse>("/api/auth/refresh", {
    method: "POST",
    body: JSON.stringify({
      refresh_token: refreshToken,
    }),
  });

export const fetchAuthenticatedUser = (accessToken: string) =>
  requestJson<AuthProfile>("/api/auth/me", {
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
  requestJson<AuthTokenResponse>("/api/auth/passkey/verify", {
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

export const fetchPasskeyCredentials = (accessToken: string) =>
  requestJson<PasskeyCredentialsResponse>("/api/auth/passkey/credentials", {
    method: "GET",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });

export const renamePasskeyCredential = (
  accessToken: string,
  credentialId: string,
  label: string,
) =>
  requestJson<PasskeyCredentialItem>(
    `/api/auth/passkey/credentials/${encodeURIComponent(credentialId)}`,
    {
      method: "PATCH",
      headers: {
        Authorization: `Bearer ${accessToken}`,
      },
      body: JSON.stringify({ label }),
    },
  );

export const revokePasskeyCredential = (
  accessToken: string,
  credentialId: string,
) =>
  requestJson<{ status: string; total_credentials: number }>(
    `/api/auth/passkey/credentials/${encodeURIComponent(credentialId)}`,
    {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${accessToken}`,
      },
    },
  );

export const fetchPasskeyPolicy = (accessToken: string) =>
  requestJson<PasskeyPolicyResponse>("/api/auth/passkey/policy", {
    method: "GET",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });

export const updatePasskeyPolicy = (
  accessToken: string,
  requirePasskeyAfterPasswordLogin: boolean,
) =>
  requestJson<PasskeyPolicyResponse>("/api/auth/passkey/policy", {
    method: "PATCH",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
    body: JSON.stringify({
      require_passkey_after_password_login: requirePasskeyAfterPasswordLogin,
    }),
  });
