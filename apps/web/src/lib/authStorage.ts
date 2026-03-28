import type { AuthUser } from "./authClient";

export type AuthSession = {
  accessToken: string;
  refreshToken: string;
  tokenType: string;
  expiresIn: number;
  user: AuthUser;
};

export const AUTH_SESSION_CHANGED_EVENT = "tiskerti.auth.session.changed";

const AUTH_STORAGE_KEY = "tiskerti.auth.session";

const notifyAuthSessionChanged = (): void => {
  if (typeof window === "undefined") {
    return;
  }

  window.dispatchEvent(new Event(AUTH_SESSION_CHANGED_EVENT));
};

export const saveAuthSession = (session: AuthSession): void => {
  localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(session));
  notifyAuthSessionChanged();
};

export const loadAuthSession = (): AuthSession | null => {
  const raw = localStorage.getItem(AUTH_STORAGE_KEY);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as AuthSession;

    if (
      typeof parsed.accessToken !== "string" ||
      typeof parsed.refreshToken !== "string" ||
      typeof parsed.tokenType !== "string" ||
      typeof parsed.expiresIn !== "number" ||
      typeof parsed.user?.email !== "string"
    ) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
};

export const clearAuthSession = (): void => {
  localStorage.removeItem(AUTH_STORAGE_KEY);
  notifyAuthSessionChanged();
};
