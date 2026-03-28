import { NavLink, Outlet, type NavLinkRenderProps } from "react-router-dom";

const getNavClass = ({ isActive }: NavLinkRenderProps) =>
  isActive ? "nav-link nav-link-active" : "nav-link";

export function AppLayout() {
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
          <span className="nav-muted">Venues</span>
          <span className="nav-muted">Schedule</span>
          <NavLink to="/tickets" className={getNavClass}>
            My Tickets
          </NavLink>
          <NavLink to="/admin" className={getNavClass}>
            Admin
          </NavLink>
        </nav>
        <NavLink to="/login" className="login-link">
          Login
        </NavLink>
      </header>
      <main id="main-content" className="page-content" tabIndex={-1}>
        <Outlet />
      </main>
    </div>
  );
}
