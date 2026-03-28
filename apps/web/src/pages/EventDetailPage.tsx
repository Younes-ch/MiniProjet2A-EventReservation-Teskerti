import { useEffect, useMemo, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { fetchPublicEventBySlug, type PublicEvent } from "../lib/eventsClient";

const toneClassByToken: Record<string, string> = {
  indigo: "home-event-tone-indigo",
  cyan: "home-event-tone-cyan",
  amber: "home-event-tone-amber",
};

const getToneClass = (event: PublicEvent): string =>
  toneClassByToken[event.visual_tone] ?? "home-event-tone-indigo";

const formatDateTime = (startsAt: string): string => {
  const parsedDate = new Date(startsAt);

  if (Number.isNaN(parsedDate.getTime())) {
    return "Date to be announced";
  }

  return parsedDate.toLocaleString("en-US", {
    weekday: "short",
    month: "long",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatPrice = (event: PublicEvent): string => {
  try {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: event.currency,
      maximumFractionDigits: 2,
    }).format(event.price_amount);
  } catch {
    return `$${event.price_amount.toFixed(2)}`;
  }
};

const buildAvailabilityBadge = (event: PublicEvent): string => {
  if (event.seats_available <= 0) {
    return "Sold out";
  }

  if (event.seats_available <= 15) {
    return `${event.seats_available} seats left`;
  }

  return `${event.seats_available} seats available`;
};

export function EventDetailPage() {
  const { eventSlug } = useParams<{ eventSlug: string }>();
  const [event, setEvent] = useState<PublicEvent | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    if (!eventSlug) {
      setErrorMessage("Event not found.");
      setIsLoading(false);
      return () => {
        isMounted = false;
      };
    }

    const loadEvent = async () => {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const payload = await fetchPublicEventBySlug(eventSlug);

        if (!isMounted) {
          return;
        }

        setEvent(payload);
      } catch (error) {
        if (!isMounted) {
          return;
        }

        if (error instanceof Error && error.message === "event_not_found") {
          setErrorMessage("This event no longer exists.");
        } else {
          setErrorMessage("Unable to load event details right now.");
        }
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    void loadEvent();

    return () => {
      isMounted = false;
    };
  }, [eventSlug]);

  const toneClass = useMemo(() => {
    if (!event) {
      return "home-event-tone-indigo";
    }

    return getToneClass(event);
  }, [event]);

  if (isLoading) {
    return (
      <section className="event-detail-shell">
        <p className="home-api-state" role="status">
          Loading event details...
        </p>
      </section>
    );
  }

  if (!event || errorMessage) {
    return (
      <section className="event-detail-shell">
        <p className="home-api-state home-api-state-error" role="alert">
          {errorMessage ?? "Unable to load event details right now."}
        </p>
        <div className="event-detail-actions">
          <Link to="/" className="button-secondary">
            Back to events
          </Link>
        </div>
      </section>
    );
  }

  return (
    <>
      <section
        className="event-detail-hero"
        aria-labelledby="event-detail-title"
      >
        <div className="event-detail-copy">
          <p className="eyebrow">{event.category}</p>
          <h1 id="event-detail-title">{event.title}</h1>
          <p>{event.summary}</p>
          <div className="event-detail-actions">
            <Link
              to={`/reserve?event=${encodeURIComponent(event.slug)}`}
              className="button-primary"
            >
              Reserve now
            </Link>
            <Link to="/" className="button-secondary">
              All events
            </Link>
          </div>
        </div>
        <div className={`event-detail-visual ${toneClass}`}>
          <span>{buildAvailabilityBadge(event)}</span>
        </div>
      </section>

      <section className="event-detail-grid" aria-label="Event details">
        <article className="event-detail-card">
          <h2>Schedule</h2>
          <p>{formatDateTime(event.starts_at)}</p>
        </article>
        <article className="event-detail-card">
          <h2>Location</h2>
          <p>{event.location}</p>
          <small>{event.city}</small>
        </article>
        <article className="event-detail-card">
          <h2>Ticket</h2>
          <p>{formatPrice(event)}</p>
          <small>{buildAvailabilityBadge(event)}</small>
        </article>
      </section>

      <section className="event-detail-note" aria-label="Attendee notice">
        <h2>Before you book</h2>
        <p>
          Bring a valid ID at check-in and arrive at least 30 minutes before the
          event starts to avoid queue delays.
        </p>
      </section>
    </>
  );
}
