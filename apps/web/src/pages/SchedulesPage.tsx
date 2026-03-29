import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { fetchPublicEvents, type PublicEvent } from "../lib/eventsClient";

type ScheduleRow = {
  id: number;
  slug: string;
  title: string;
  location: string;
  city: string;
  startsAtLabel: string;
  availabilityLabel: string;
};

const formatStartsAt = (value: string): string => {
  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return "Date TBD";
  }

  return parsed.toLocaleString("en-US", {
    month: "short",
    day: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
};

const buildAvailabilityLabel = (event: PublicEvent): string => {
  if (event.seats_available <= 0) {
    return "Sold out";
  }

  if (event.seats_available <= 15) {
    return `${event.seats_available} seats left`;
  }

  return `${event.seats_available} seats available`;
};

const buildScheduleRows = (events: PublicEvent[]): ScheduleRow[] =>
  [...events]
    .sort(
      (left, right) =>
        new Date(left.starts_at).getTime() -
        new Date(right.starts_at).getTime(),
    )
    .map((event) => ({
      id: event.id,
      slug: event.slug,
      title: event.title,
      location: event.location,
      city: event.city,
      startsAtLabel: formatStartsAt(event.starts_at),
      availabilityLabel: buildAvailabilityLabel(event),
    }));

export function SchedulesPage() {
  const [events, setEvents] = useState<PublicEvent[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    const load = async () => {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const payload = await fetchPublicEvents();
        if (!isMounted) {
          return;
        }

        setEvents(payload);
      } catch {
        if (!isMounted) {
          return;
        }

        setErrorMessage("Unable to load schedules right now.");
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    void load();

    return () => {
      isMounted = false;
    };
  }, []);

  const rows = useMemo(() => buildScheduleRows(events), [events]);

  return (
    <section className="section-block" aria-labelledby="schedule-heading">
      <p className="eyebrow">Timeline</p>
      <h1 id="schedule-heading">Schedules</h1>
      <p className="section-copy">
        View all upcoming events in chronological order.
      </p>

      {isLoading ? (
        <p className="home-api-state" role="status">
          Loading schedules...
        </p>
      ) : errorMessage ? (
        <p className="home-api-state home-api-state-error" role="alert">
          {errorMessage}
        </p>
      ) : rows.length === 0 ? (
        <p className="home-api-state" role="status">
          No schedules available yet.
        </p>
      ) : (
        <div className="table-shell" aria-label="Event schedule table">
          <div className="table-row table-header admin-events-header">
            <span>Event</span>
            <span>Starts at</span>
            <span>Venue</span>
            <span>Availability</span>
          </div>

          {rows.map((row) => (
            <div key={row.id} className="table-row admin-events-row">
              <span>
                <strong>{row.title}</strong>
                <small>
                  <Link to={`/events/${row.slug}`}>Open event</Link>
                </small>
              </span>
              <span>
                <strong>{row.startsAtLabel}</strong>
              </span>
              <span>
                <strong>{row.location}</strong>
                <small>{row.city}</small>
              </span>
              <span className="admin-status-chip">{row.availabilityLabel}</span>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}
