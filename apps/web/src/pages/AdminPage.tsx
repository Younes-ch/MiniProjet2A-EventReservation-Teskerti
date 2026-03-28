import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import {
  fetchAuthenticatedUser,
  refreshAccessToken,
  type AuthUser,
} from "../lib/authClient";
import { clearAuthSession, loadAuthSession, saveAuthSession } from "../lib/authStorage";
import { fetchPublicEvents, type PublicEvent } from "../lib/eventsClient";

type DashboardState = "loading" | "ready" | "unauthenticated" | "error";

type AdminMetric = {
  label: string;
  value: string;
  trend: string;
  tone: string;
};

type AdminEventRow = {
  slug: string;
  title: string;
  venue: string;
  date: string;
  time: string;
  status: string;
};

type AdminInsight = {
  label: string;
  detail: string;
  note: string;
};

const formatDate = (startsAt: string): string => {
  const parsedDate = new Date(startsAt);

  if (Number.isNaN(parsedDate.getTime())) {
    return "Date TBD";
  }

  return parsedDate.toLocaleDateString("en-US", {
    month: "short",
    day: "2-digit",
    year: "numeric",
  });
};

const formatTime = (startsAt: string): string => {
  const parsedDate = new Date(startsAt);

  if (Number.isNaN(parsedDate.getTime())) {
    return "Time TBD";
  }

  return parsedDate.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
};

const getEventStatus = (event: PublicEvent): string => {
  if (event.seats_available <= 0) {
    return "Sold Out";
  }

  if (event.seats_available <= 15) {
    return "Almost Full";
  }

  return "Active";
};

const buildMetrics = (events: PublicEvent[]): AdminMetric[] => {
  const totalEvents = events.length;
  const totalReservations = events.reduce(
    (sum, event) => sum + Math.max(event.seats_total - event.seats_available, 0),
    0,
  );
  const upcomingEvents = events.filter((event) => event.seats_available > 0).length;

  return [
    {
      label: "Total events",
      value: totalEvents.toString(),
      trend: "Live",
      tone: "metric-tone-1",
    },
    {
      label: "Total reservations",
      value: totalReservations.toString(),
      trend: "Synced",
      tone: "metric-tone-2",
    },
    {
      label: "Upcoming events",
      value: upcomingEvents.toString(),
      trend: "Open",
      tone: "metric-tone-3",
    },
  ];
};

const buildRecentEvents = (events: PublicEvent[]): AdminEventRow[] =>
  events.slice(0, 3).map((event) => ({
    slug: event.slug,
    title: event.title,
    venue: `${event.location}, ${event.city}`,
    date: formatDate(event.starts_at),
    time: formatTime(event.starts_at),
    status: getEventStatus(event),
  }));

const buildInsights = (events: PublicEvent[]): AdminInsight[] => {
  if (events.length === 0) {
    return [
      {
        label: "Most popular",
        detail: "No events published yet",
        note: "Create your first event to unlock insights",
      },
      {
        label: "Capacity usage",
        detail: "0%",
        note: "No seat data available",
      },
    ];
  }

  const mostPopular = events.reduce((best, current) => {
    const bestBooked = best.seats_total - best.seats_available;
    const currentBooked = current.seats_total - current.seats_available;
    return currentBooked > bestBooked ? current : best;
  });

  const totalSeats = events.reduce((sum, event) => sum + event.seats_total, 0);
  const bookedSeats = events.reduce(
    (sum, event) => sum + Math.max(event.seats_total - event.seats_available, 0),
    0,
  );
  const usage = totalSeats > 0 ? (bookedSeats / totalSeats) * 100 : 0;

  return [
    {
      label: "Most popular",
      detail: mostPopular.title,
      note: `${Math.max(mostPopular.seats_total - mostPopular.seats_available, 0)} seats reserved`,
    },
    {
      label: "Capacity usage",
      detail: `${usage.toFixed(1)}%`,
      note: `${bookedSeats} of ${totalSeats} seats reserved`,
    },
  ];
};

export function AdminPage() {
  const [dashboardState, setDashboardState] = useState<DashboardState>("loading");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [authUser, setAuthUser] = useState<AuthUser | null>(null);
  const [events, setEvents] = useState<PublicEvent[]>([]);
  const [reloadNonce, setReloadNonce] = useState(0);

  useEffect(() => {
    let isMounted = true;

    const loadDashboard = async () => {
      setDashboardState("loading");
      setErrorMessage(null);

      const session = loadAuthSession();
      if (!session) {
        if (isMounted) {
          setDashboardState("unauthenticated");
        }

        return;
      }

      let userProfile: AuthUser | null = null;

      try {
        userProfile = await fetchAuthenticatedUser(session.accessToken);
      } catch (error) {
        if (error instanceof Error && error.message === "invalid_access_token") {
          try {
            const refreshed = await refreshAccessToken(session.refreshToken);
            saveAuthSession({
              accessToken: refreshed.access_token,
              refreshToken: refreshed.refresh_token,
              tokenType: refreshed.token_type,
              expiresIn: refreshed.expires_in,
              user: refreshed.user,
            });
            userProfile = refreshed.user;
          } catch {
            clearAuthSession();
            if (isMounted) {
              setDashboardState("unauthenticated");
            }

            return;
          }
        } else {
          clearAuthSession();
          if (isMounted) {
            setDashboardState("unauthenticated");
          }

          return;
        }
      }

      try {
        const eventsPayload = await fetchPublicEvents();

        if (!isMounted) {
          return;
        }

        setAuthUser(userProfile);
        setEvents(eventsPayload);
        setDashboardState("ready");
      } catch {
        if (!isMounted) {
          return;
        }

        setErrorMessage("Unable to load dashboard data right now.");
        setDashboardState("error");
      }
    };

    void loadDashboard();

    return () => {
      isMounted = false;
    };
  }, [reloadNonce]);

  const metrics = useMemo(() => buildMetrics(events), [events]);
  const recentEvents = useMemo(() => buildRecentEvents(events), [events]);
  const insights = useMemo(() => buildInsights(events), [events]);

  if (dashboardState === "loading") {
    return (
      <section className="section-block admin-shell">
        <p className="home-api-state" role="status">
          Loading admin dashboard...
        </p>
      </section>
    );
  }

  if (dashboardState === "unauthenticated") {
    return (
      <section className="section-block admin-shell">
        <p className="home-api-state home-api-state-error" role="alert">
          Please sign in to access the admin dashboard.
        </p>
        <div className="admin-state-actions">
          <Link to="/login" className="button-primary">
            Go to login
          </Link>
          <Link to="/" className="button-secondary">
            Back to events
          </Link>
        </div>
      </section>
    );
  }

  if (dashboardState === "error") {
    return (
      <section className="section-block admin-shell">
        <p className="home-api-state home-api-state-error" role="alert">
          {errorMessage}
        </p>
        <div className="admin-state-actions">
          <button
            type="button"
            className="button-secondary"
            onClick={() => setReloadNonce((previous) => previous + 1)}
          >
            Retry dashboard load
          </button>
        </div>
      </section>
    );
  }

  const displayName = authUser?.display_name ?? "Admin";

  return (
    <section className="section-block admin-shell">
      <header className="admin-shell-header">
        <div>
          <p className="eyebrow">Admin shell</p>
          <h1>Dashboard Overview</h1>
          <p className="section-copy">
            Welcome back, {displayName}. Here&apos;s what&apos;s happening with your
            events today.
          </p>
        </div>

        <div className="admin-search-row">
          <input
            type="search"
            className="admin-search-input"
            placeholder="Search analytics..."
            aria-label="Search analytics"
          />
          <button type="button" className="admin-alert-button">
            Alerts
          </button>
        </div>
      </header>

      <div className="admin-kpi-grid">
        {metrics.map((metric) => (
          <article key={metric.label} className="metric-card admin-metric-card">
            <span
              className={`admin-metric-dot ${metric.tone}`}
              aria-hidden="true"
            />
            <span className="admin-metric-trend">{metric.trend}</span>
            <p>{metric.label}</p>
            <h2>{metric.value}</h2>
          </article>
        ))}
      </div>

      <div className="admin-main-grid">
        <article
          className="table-shell admin-events"
          aria-label="Recent events shell"
        >
          <header className="admin-events-head">
            <h2>Recent Events</h2>
            <Link to="/tickets">View all</Link>
          </header>

          <div className="table-row table-header admin-events-header">
            <span>Event details</span>
            <span>Date and time</span>
            <span>Status</span>
          </div>

          {recentEvents.length > 0 ? (
            recentEvents.map((event) => (
              <div className="table-row admin-events-row" key={event.slug}>
                <span>
                  <strong>{event.title}</strong>
                  <small>{event.venue}</small>
                </span>
                <span>
                  <strong>{event.date}</strong>
                  <small>{event.time}</small>
                </span>
                <span className="admin-status-chip">{event.status}</span>
              </div>
            ))
          ) : (
            <p className="home-api-state">No events available for admin view yet.</p>
          )}
        </article>

        <aside className="admin-side-stack">
          <article className="admin-insight-card">
            <h2>Quick Insights</h2>
            <ul>
              {insights.map((insight) => (
                <li key={insight.label}>
                  <p>{insight.label}</p>
                  <strong>{insight.detail}</strong>
                  <span>{insight.note}</span>
                </li>
              ))}
            </ul>
            <button type="button" className="button-secondary wide">
              Export Reports
            </button>
          </article>

          <article className="admin-plan-card">
            <h2>Need a custom plan?</h2>
            <p>
              Upgrade to our enterprise architecture for unlimited events and
              advanced AI analytics.
            </p>
            <button type="button" className="button-primary">
              Explore Plans
            </button>
          </article>
        </aside>
      </div>
    </section>
  );
}
