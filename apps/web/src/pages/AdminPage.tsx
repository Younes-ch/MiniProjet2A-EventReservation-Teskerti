import {
  type ChangeEvent,
  type FormEvent,
  useEffect,
  useMemo,
  useState,
} from "react";
import { Link } from "react-router-dom";
import {
  fetchAuthenticatedUser,
  refreshAccessToken,
  type AuthUser,
} from "../lib/authClient";
import {
  clearAuthSession,
  loadAuthSession,
  saveAuthSession,
} from "../lib/authStorage";
import {
  createAdminEvent,
  deleteAdminEvent,
  fetchAdminEvents,
  updateAdminEvent,
  type PublicEvent,
  type UpsertEventPayload,
} from "../lib/eventsClient";
import {
  fetchAdminReservations,
  updateAdminReservationStatus,
  type AdminReservationItem,
  type AdminReservationStatusFilter,
  type AdminReservationsMeta,
} from "../lib/reservationsClient";

type DashboardState = "loading" | "ready" | "unauthenticated" | "error";
type EditorMode = "create" | "edit";

type AdminMetric = {
  label: string;
  value: string;
  trend: string;
  tone: string;
};

type AdminEventRow = {
  id: number;
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

type AdminReservationRow = {
  id: number;
  reservationCode: string;
  attendeeName: string;
  attendeeEmail: string;
  eventTitle: string;
  bookedAt: string;
  status: "confirmed" | "cancelled";
};

const RESERVATIONS_PER_PAGE = 6;

const isAdminReservationStatusFilter = (
  value: string,
): value is AdminReservationStatusFilter =>
  value === "all" || value === "confirmed" || value === "cancelled";

type EventEditorValues = {
  title: string;
  summary: string;
  category: string;
  location: string;
  city: string;
  startsAt: string;
  priceAmount: string;
  currency: string;
  seatsTotal: string;
  seatsAvailable: string;
  visualTone: string;
};

const defaultEditorValues: EventEditorValues = {
  title: "",
  summary: "",
  category: "",
  location: "",
  city: "",
  startsAt: "",
  priceAmount: "",
  currency: "USD",
  seatsTotal: "",
  seatsAvailable: "",
  visualTone: "indigo",
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

const buildMetrics = (events: PublicEvent[], totalReservations: number): AdminMetric[] => {
  const totalEvents = events.length;
  const upcomingEvents = events.filter(
    (event) => event.seats_available > 0,
  ).length;

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
    id: event.id,
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
    (sum, event) =>
      sum + Math.max(event.seats_total - event.seats_available, 0),
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

const formatDateTime = (dateTimeValue: string): string => {
  const parsedDate = new Date(dateTimeValue);

  if (Number.isNaN(parsedDate.getTime())) {
    return "Date TBD";
  }

  return parsedDate.toLocaleString("en-US", {
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
};

const buildReservationRows = (
  reservations: AdminReservationItem[],
): AdminReservationRow[] =>
  reservations.map((reservation) => ({
    id: reservation.id,
    reservationCode: reservation.reservation_id,
    attendeeName: reservation.attendee_name,
    attendeeEmail: reservation.attendee_email,
    eventTitle: reservation.event.title ?? "Unknown event",
    bookedAt: formatDateTime(reservation.created_at),
    status: reservation.status === "cancelled" ? "cancelled" : "confirmed",
  }));

const getReservationStatusLabel = (
  status: "confirmed" | "cancelled",
): string => (status === "cancelled" ? "Cancelled" : "Confirmed");

const toDatetimeLocalValue = (isoDateTime: string): string => {
  const parsedDate = new Date(isoDateTime);

  if (Number.isNaN(parsedDate.getTime())) {
    return "";
  }

  const timezoneOffset = parsedDate.getTimezoneOffset() * 60000;
  const localDate = new Date(parsedDate.getTime() - timezoneOffset);

  return localDate.toISOString().slice(0, 16);
};

const parseDatetimeLocalToIso = (value: string): string | null => {
  const parsedDate = new Date(value);

  if (Number.isNaN(parsedDate.getTime())) {
    return null;
  }

  return parsedDate.toISOString();
};

const mapCrudErrorMessage = (error: unknown): string => {
  if (!(error instanceof Error)) {
    return "Unable to complete this admin action right now.";
  }

  if (error.message === "invalid_json_payload") {
    return "The event payload format is invalid.";
  }

  if (error.message === "event_payload_invalid") {
    return "Some event fields are invalid. Check date, seats, and price values.";
  }

  if (error.message === "event_not_found") {
    return "This event no longer exists.";
  }

  if (error.message === "reservation_not_found") {
    return "This reservation no longer exists.";
  }

  if (error.message === "invalid_reservation_status") {
    return "Reservation status is invalid.";
  }

  if (error.message === "invalid_reservation_status_filter") {
    return "Reservation status filter is invalid.";
  }

  if (
    error.message === "invalid_access_token" ||
    error.message === "missing_bearer_token" ||
    error.message === "insufficient_role"
  ) {
    return "Admin authorization failed. Please sign in again.";
  }

  return "Unable to complete this admin action right now.";
};

const buildPayloadFromEditor = (
  values: EventEditorValues,
): UpsertEventPayload | null => {
  const startsAt = parseDatetimeLocalToIso(values.startsAt);
  const priceAmount = Number(values.priceAmount);
  const seatsTotal = Number(values.seatsTotal);
  const seatsAvailable = Number(values.seatsAvailable);

  if (
    !startsAt ||
    !Number.isFinite(priceAmount) ||
    !Number.isInteger(seatsTotal) ||
    !Number.isInteger(seatsAvailable)
  ) {
    return null;
  }

  return {
    title: values.title.trim(),
    summary: values.summary.trim(),
    category: values.category.trim(),
    location: values.location.trim(),
    city: values.city.trim(),
    starts_at: startsAt,
    price_amount: priceAmount,
    currency: values.currency.trim().toUpperCase(),
    seats_total: seatsTotal,
    seats_available: seatsAvailable,
    visual_tone: values.visualTone,
  };
};

export function AdminPage() {
  const [dashboardState, setDashboardState] =
    useState<DashboardState>("loading");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [authUser, setAuthUser] = useState<AuthUser | null>(null);
  const [events, setEvents] = useState<PublicEvent[]>([]);
  const [reservations, setReservations] = useState<AdminReservationItem[]>([]);
  const [reservationMeta, setReservationMeta] =
    useState<AdminReservationsMeta | null>(null);
  const [reloadNonce, setReloadNonce] = useState(0);
  const [accessToken, setAccessToken] = useState<string | null>(null);
  const [reservationPage, setReservationPage] = useState(1);
  const [reservationStatusFilter, setReservationStatusFilter] =
    useState<AdminReservationStatusFilter>("all");
  const [reservationQueryInput, setReservationQueryInput] = useState("");
  const [reservationSearchQuery, setReservationSearchQuery] = useState("");
  const [isEditorOpen, setEditorOpen] = useState(false);
  const [editorMode, setEditorMode] = useState<EditorMode>("create");
  const [editingEventId, setEditingEventId] = useState<number | null>(null);
  const [editorValues, setEditorValues] =
    useState<EventEditorValues>(defaultEditorValues);
  const [actionErrorMessage, setActionErrorMessage] = useState<string | null>(
    null,
  );
  const [isActionSubmitting, setActionSubmitting] = useState(false);

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
      let validAccessToken = session.accessToken;

      try {
        userProfile = await fetchAuthenticatedUser(validAccessToken);
      } catch (error) {
        if (
          error instanceof Error &&
          error.message === "invalid_access_token"
        ) {
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
            validAccessToken = refreshed.access_token;
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
        const [eventsPayload, reservationsPayload] = await Promise.all([
          fetchAdminEvents(validAccessToken),
          fetchAdminReservations(validAccessToken, {
            page: reservationPage,
            perPage: RESERVATIONS_PER_PAGE,
            status: reservationStatusFilter,
            query: reservationSearchQuery,
          }),
        ]);

        if (!isMounted) {
          return;
        }

        setAuthUser(userProfile);
        setEvents(eventsPayload);
        setReservations(reservationsPayload.items);
        setReservationMeta(reservationsPayload.meta);
        if (reservationsPayload.meta.page !== reservationPage) {
          setReservationPage(reservationsPayload.meta.page);
        }
        setAccessToken(validAccessToken);
        setActionErrorMessage(null);
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
  }, [reloadNonce, reservationPage, reservationStatusFilter, reservationSearchQuery]);

  const metrics = useMemo(
    () => buildMetrics(events, reservationMeta?.total_items ?? reservations.length),
    [events, reservationMeta, reservations.length],
  );
  const recentEvents = useMemo(() => buildRecentEvents(events), [events]);
  const insights = useMemo(() => buildInsights(events), [events]);
  const recentReservations = useMemo(
    () => buildReservationRows(reservations),
    [reservations],
  );

  const isReservationFilterActive =
    reservationStatusFilter !== "all" || reservationSearchQuery.length > 0;

  const canGoToPreviousReservationPage = (reservationMeta?.page ?? 1) > 1;
  const canGoToNextReservationPage =
    reservationMeta !== null && reservationMeta.page < reservationMeta.total_pages;

  const runWithFreshAccessToken = async <T,>(
    operation: (token: string) => Promise<T>,
  ): Promise<T> => {
    const session = loadAuthSession();
    if (!session) {
      clearAuthSession();
      setDashboardState("unauthenticated");
      throw new Error("missing_bearer_token");
    }

    const token = accessToken ?? session.accessToken;

    try {
      return await operation(token);
    } catch (error) {
      if (
        !(error instanceof Error) ||
        error.message !== "invalid_access_token"
      ) {
        throw error;
      }

      const refreshed = await refreshAccessToken(session.refreshToken);
      saveAuthSession({
        accessToken: refreshed.access_token,
        refreshToken: refreshed.refresh_token,
        tokenType: refreshed.token_type,
        expiresIn: refreshed.expires_in,
        user: refreshed.user,
      });

      setAccessToken(refreshed.access_token);

      return operation(refreshed.access_token);
    }
  };

  const openCreateEditor = () => {
    setEditorMode("create");
    setEditingEventId(null);
    setEditorValues({
      ...defaultEditorValues,
      startsAt: toDatetimeLocalValue(new Date().toISOString()),
      seatsTotal: "100",
      seatsAvailable: "100",
      priceAmount: "0",
    });
    setActionErrorMessage(null);
    setEditorOpen(true);
  };

  const openEditEditor = (event: PublicEvent) => {
    setEditorMode("edit");
    setEditingEventId(event.id);
    setEditorValues({
      title: event.title,
      summary: event.summary,
      category: event.category,
      location: event.location,
      city: event.city,
      startsAt: toDatetimeLocalValue(event.starts_at),
      priceAmount: event.price_amount.toString(),
      currency: event.currency,
      seatsTotal: event.seats_total.toString(),
      seatsAvailable: event.seats_available.toString(),
      visualTone: event.visual_tone,
    });
    setActionErrorMessage(null);
    setEditorOpen(true);
  };

  const handleEditorChange =
    (field: keyof EventEditorValues) =>
    (
      event:
        | ChangeEvent<HTMLInputElement>
        | ChangeEvent<HTMLTextAreaElement>
        | ChangeEvent<HTMLSelectElement>,
    ) => {
      const nextValue = event.target.value;
      setEditorValues((current) => ({
        ...current,
        [field]: nextValue,
      }));
    };

  const handleEditorSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const payload = buildPayloadFromEditor(editorValues);
    if (!payload) {
      setActionErrorMessage(
        "Please provide valid date, numeric price, and seat values.",
      );
      return;
    }

    setActionSubmitting(true);
    setActionErrorMessage(null);

    try {
      if (editorMode === "create") {
        await runWithFreshAccessToken((token) =>
          createAdminEvent(token, payload),
        );
      } else if (editingEventId !== null) {
        await runWithFreshAccessToken((token) =>
          updateAdminEvent(token, editingEventId, payload),
        );
      }

      setEditorOpen(false);
      setReloadNonce((previous) => previous + 1);
    } catch (error) {
      setActionErrorMessage(mapCrudErrorMessage(error));
    } finally {
      setActionSubmitting(false);
    }
  };

  const handleDeleteEvent = async (eventId: number, title: string) => {
    if (!window.confirm(`Delete event "${title}"?`)) {
      return;
    }

    setActionSubmitting(true);
    setActionErrorMessage(null);

    try {
      await runWithFreshAccessToken((token) =>
        deleteAdminEvent(token, eventId),
      );
      setReloadNonce((previous) => previous + 1);
    } catch (error) {
      setActionErrorMessage(mapCrudErrorMessage(error));
    } finally {
      setActionSubmitting(false);
    }
  };

  const handleToggleReservationStatus = async (
    reservation: AdminReservationRow,
  ) => {
    const nextStatus =
      reservation.status === "confirmed" ? "cancelled" : "confirmed";

    setActionSubmitting(true);
    setActionErrorMessage(null);

    try {
      await runWithFreshAccessToken((token) =>
        updateAdminReservationStatus(token, reservation.id, nextStatus),
      );
      setReloadNonce((previous) => previous + 1);
    } catch (error) {
      setActionErrorMessage(mapCrudErrorMessage(error));
    } finally {
      setActionSubmitting(false);
    }
  };

  const handleReservationStatusFilterChange = (
    event: ChangeEvent<HTMLSelectElement>,
  ) => {
    const nextValue = event.target.value;
    if (!isAdminReservationStatusFilter(nextValue)) {
      return;
    }

    setReservationStatusFilter(nextValue);
    setReservationPage(1);
  };

  const handleReservationSearchSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setReservationSearchQuery(reservationQueryInput.trim());
    setReservationPage(1);
  };

  const handleReservationSearchReset = () => {
    setReservationQueryInput("");
    setReservationSearchQuery("");
    setReservationPage(1);
  };

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
            Welcome back, {displayName}. Here&apos;s what&apos;s happening with
            your events today.
          </p>
        </div>

        <div className="admin-search-row">
          <button
            type="button"
            className="button-secondary"
            onClick={openCreateEditor}
          >
            Create Event
          </button>
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

      {actionErrorMessage ? (
        <p className="home-api-state home-api-state-error" role="alert">
          {actionErrorMessage}
        </p>
      ) : null}

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
            <span>Actions</span>
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
                <span className="admin-row-actions">
                  <button
                    type="button"
                    className="admin-row-action-button"
                    onClick={() => {
                      const fullEvent = events.find(
                        (item) => item.id === event.id,
                      );
                      if (fullEvent) {
                        openEditEditor(fullEvent);
                      }
                    }}
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    className="admin-row-action-button admin-row-action-danger"
                    onClick={() =>
                      void handleDeleteEvent(event.id, event.title)
                    }
                    disabled={isActionSubmitting}
                  >
                    Delete
                  </button>
                </span>
              </div>
            ))
          ) : (
            <p className="home-api-state">
              No events available for admin view yet.
            </p>
          )}

          {isEditorOpen ? (
            <section className="admin-editor-card" aria-label="Event editor">
              <header>
                <h3>
                  {editorMode === "create" ? "Create Event" : "Edit Event"}
                </h3>
              </header>

              <form className="admin-editor-form" onSubmit={handleEditorSubmit}>
                <div className="admin-editor-grid">
                  <label>
                    Title
                    <input
                      type="text"
                      required
                      value={editorValues.title}
                      onChange={handleEditorChange("title")}
                    />
                  </label>

                  <label>
                    Category
                    <input
                      type="text"
                      required
                      value={editorValues.category}
                      onChange={handleEditorChange("category")}
                    />
                  </label>

                  <label className="admin-editor-full">
                    Summary
                    <textarea
                      required
                      rows={3}
                      value={editorValues.summary}
                      onChange={handleEditorChange("summary")}
                    />
                  </label>

                  <label>
                    Location
                    <input
                      type="text"
                      required
                      value={editorValues.location}
                      onChange={handleEditorChange("location")}
                    />
                  </label>

                  <label>
                    City
                    <input
                      type="text"
                      required
                      value={editorValues.city}
                      onChange={handleEditorChange("city")}
                    />
                  </label>

                  <label>
                    Start Date & Time
                    <input
                      type="datetime-local"
                      required
                      value={editorValues.startsAt}
                      onChange={handleEditorChange("startsAt")}
                    />
                  </label>

                  <label>
                    Price
                    <input
                      type="number"
                      required
                      min="0"
                      step="0.01"
                      value={editorValues.priceAmount}
                      onChange={handleEditorChange("priceAmount")}
                    />
                  </label>

                  <label>
                    Currency
                    <input
                      type="text"
                      required
                      minLength={3}
                      maxLength={3}
                      value={editorValues.currency}
                      onChange={handleEditorChange("currency")}
                    />
                  </label>

                  <label>
                    Seats Total
                    <input
                      type="number"
                      required
                      min="1"
                      step="1"
                      value={editorValues.seatsTotal}
                      onChange={handleEditorChange("seatsTotal")}
                    />
                  </label>

                  <label>
                    Seats Available
                    <input
                      type="number"
                      required
                      min="0"
                      step="1"
                      value={editorValues.seatsAvailable}
                      onChange={handleEditorChange("seatsAvailable")}
                    />
                  </label>

                  <label>
                    Visual Tone
                    <select
                      value={editorValues.visualTone}
                      onChange={handleEditorChange("visualTone")}
                    >
                      <option value="indigo">Indigo</option>
                      <option value="cyan">Cyan</option>
                      <option value="amber">Amber</option>
                    </select>
                  </label>
                </div>

                <div className="admin-editor-actions">
                  <button
                    type="submit"
                    className="button-primary"
                    disabled={isActionSubmitting}
                  >
                    {isActionSubmitting
                      ? "Saving..."
                      : editorMode === "create"
                        ? "Create Event"
                        : "Save Changes"}
                  </button>
                  <button
                    type="button"
                    className="button-secondary"
                    onClick={() => setEditorOpen(false)}
                    disabled={isActionSubmitting}
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </section>
          ) : null}
        </article>

        <aside className="admin-side-stack">
          <article className="admin-reservations-card">
            <header className="admin-reservations-head">
              <h2>Reservation Queue</h2>
              <small>
                {reservationMeta?.total_items ?? reservations.length} total
              </small>
            </header>

            <div className="admin-reservation-controls">
              <label className="admin-reservation-filter-label">
                Status
                <select
                  value={reservationStatusFilter}
                  onChange={handleReservationStatusFilterChange}
                >
                  <option value="all">All</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </label>

              <form
                className="admin-reservation-search-form"
                onSubmit={handleReservationSearchSubmit}
              >
                <input
                  type="search"
                  value={reservationQueryInput}
                  onChange={(event) => setReservationQueryInput(event.target.value)}
                  placeholder="Search name, email, event"
                  aria-label="Search reservations"
                />
                <button type="submit" className="admin-row-action-button">
                  Apply
                </button>
                {reservationSearchQuery.length > 0 ? (
                  <button
                    type="button"
                    className="admin-row-action-button"
                    onClick={handleReservationSearchReset}
                  >
                    Clear
                  </button>
                ) : null}
              </form>
            </div>

            {recentReservations.length > 0 ? (
              <ul className="admin-reservation-list">
                {recentReservations.map((reservation) => (
                  <li
                    key={reservation.reservationCode}
                    className="admin-reservation-item"
                  >
                    <div className="admin-reservation-main">
                      <strong>{reservation.attendeeName}</strong>
                      <small>{reservation.attendeeEmail}</small>
                      <small>{reservation.eventTitle}</small>
                    </div>
                    <div className="admin-reservation-meta">
                      <span
                        className={`admin-reservation-status admin-reservation-status-${reservation.status}`}
                      >
                        {getReservationStatusLabel(reservation.status)}
                      </span>
                      <small>{reservation.bookedAt}</small>
                      <button
                        type="button"
                        className="admin-row-action-button"
                        onClick={() =>
                          void handleToggleReservationStatus(reservation)
                        }
                        disabled={isActionSubmitting}
                      >
                        {reservation.status === "confirmed"
                          ? "Cancel"
                          : "Reopen"}
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="home-api-state">
                {isReservationFilterActive
                  ? "No reservations match the current filters."
                  : "No reservations have been placed yet."}
              </p>
            )}

            {reservationMeta && reservationMeta.total_pages > 1 ? (
              <div className="admin-reservation-pagination">
                <button
                  type="button"
                  className="admin-row-action-button"
                  onClick={() =>
                    setReservationPage((current) => Math.max(1, current - 1))
                  }
                  disabled={!canGoToPreviousReservationPage}
                >
                  Previous
                </button>
                <span>
                  Page {reservationMeta.page} of {reservationMeta.total_pages}
                </span>
                <button
                  type="button"
                  className="admin-row-action-button"
                  onClick={() =>
                    setReservationPage((current) => current + 1)
                  }
                  disabled={!canGoToNextReservationPage}
                >
                  Next
                </button>
              </div>
            ) : null}
          </article>

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
