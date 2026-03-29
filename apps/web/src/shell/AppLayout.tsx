import { useEffect, useState } from "react";
import {
  NavLink,
  Outlet,
  useNavigate,
  type NavLinkRenderProps,
} from "react-router-dom";
import { logoutWithRefreshToken } from "../lib/authClient";
import {
  AUTH_SESSION_CHANGED_EVENT,
  clearAuthSession,
  loadAuthSession,
} from "../lib/authStorage";

const getNavClass = ({ isActive }: NavLinkRenderProps) =>
  isActive ? "nav-link nav-link-active" : "nav-link";

export function AppLayout() {
  const navigate = useNavigate();
  const [isAuthenticated, setAuthenticated] = useState(
    () => loadAuthSession() !== null,
  );
  const [isLoggingOut, setLoggingOut] = useState(false);

  useEffect(() => {
    const syncAuthenticationState = () => {
      setAuthenticated(loadAuthSession() !== null);
    };

    syncAuthenticationState();
    window.addEventListener("storage", syncAuthenticationState);
    window.addEventListener(
      AUTH_SESSION_CHANGED_EVENT,
      syncAuthenticationState,
    );

    return () => {
      window.removeEventListener("storage", syncAuthenticationState);
      window.removeEventListener(
        AUTH_SESSION_CHANGED_EVENT,
        syncAuthenticationState,
      );
    };
  }, []);

  const handleLogout = async () => {
    const session = loadAuthSession();

    setLoggingOut(true);

    try {
      if (session?.refreshToken) {
        await logoutWithRefreshToken(session.refreshToken);
      }
    } catch {
      // Client-side session clear is still required when logout API fails.
    } finally {
      clearAuthSession();
      setLoggingOut(false);
      navigate("/login");
    }
  };

  return (
    <div className="app-shell">
      <a href="#main-content" className="skip-link">
        Skip to main content
      </a>
      <header className="topbar">
        <NavLink to="/" className="brand-link">
          Aura Events
        </NavLink>
        <nav className="topbar-nav" aria-label="Main">
          <NavLink to="/" end className={getNavClass}>
            Events
          </NavLink>
          <NavLink to="/venues" className={getNavClass}>
            Venues
          </NavLink>
          <NavLink to="/schedules" className={getNavClass}>
            Schedules
          </NavLink>
          <NavLink to="/tickets" className={getNavClass}>
            My Tickets
          </NavLink>
          <NavLink to="/admin" className={getNavClass}>
            Admin
          </NavLink>
        </nav>
        {isAuthenticated ? (
          <button
            type="button"
            className="login-link logout-link"
            onClick={() => void handleLogout()}
            disabled={isLoggingOut}
          >
            {isLoggingOut ? "Logging out..." : "Logout"}
          </button>
        ) : (
          <NavLink to="/login" className="login-link">
            Login
          </NavLink>
        )}
      </header>
      <main id="main-content" className="page-content" tabIndex={-1}>
        <Outlet />
      </main>
    </div>
  );
}
