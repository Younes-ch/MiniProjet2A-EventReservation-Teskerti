import {
  type ChangeEvent,
  type FormEvent,
  useEffect,
  useMemo,
  useState,
} from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { fetchPublicEventBySlug, type PublicEvent } from "../lib/eventsClient";
import { createReservation } from "../lib/reservationsClient";
import { saveLatestTicket } from "../lib/ticketStorage";

type ReservationFormValues = {
  fullName: string;
  email: string;
  phone: string;
};

type ReservationFormErrors = Partial<
  Record<keyof ReservationFormValues, string>
>;

type ReservationConfirmationState = {
  attendeeName: string;
  attendeeEmail: string;
  attendeePhone: string;
  reservationId: string;
  eventTitle: string;
  date: string;
  time: string;
  location: string;
  qrCodeToken: string;
  ticketDownloadUrl: string;
};

const FALLBACK_EVENT_SLUG = "midnight-resonance-2-0";

const initialFormValues: ReservationFormValues = {
  fullName: "",
  email: "",
  phone: "",
};

const validateReservationForm = (
  values: ReservationFormValues,
): ReservationFormErrors => {
  const errors: ReservationFormErrors = {};
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const phoneDigits = values.phone.replace(/\D/g, "");

  if (values.fullName.trim().length < 3) {
    errors.fullName = "Enter at least 3 characters for your name.";
  }

  if (!emailPattern.test(values.email.trim())) {
    errors.email = "Enter a valid email address.";
  }

  if (phoneDigits.length < 10) {
    errors.phone = "Enter a valid phone number with at least 10 digits.";
  }

  return errors;
};

const formatEventPrice = (event: PublicEvent): string => {
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

const parseEventDate = (startsAt: string): Date | null => {
  const parsed = new Date(startsAt);

  if (Number.isNaN(parsed.getTime())) {
    return null;
  }

  return parsed;
};

const buildEventDateLabel = (startsAt: string): string => {
  const parsed = parseEventDate(startsAt);

  if (!parsed) {
    return "Date to be announced";
  }

  return parsed.toLocaleDateString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
};

const buildEventTimeLabel = (startsAt: string): string => {
  const parsed = parseEventDate(startsAt);

  if (!parsed) {
    return "Time to be announced";
  }

  return parsed.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
};

const mapReservationErrorMessage = (error: unknown): string => {
  if (!(error instanceof Error)) {
    return "Unable to confirm reservation right now.";
  }

  if (error.message === "reservation_fields_required") {
    return "Please complete all reservation fields.";
  }

  if (error.message === "invalid_reservation_payload") {
    return "Email or phone number format is invalid.";
  }

  if (error.message === "event_not_found") {
    return "Selected event is no longer available.";
  }

  if (error.message === "invalid_json_payload") {
    return "Unexpected reservation payload format.";
  }

  return "Unable to confirm reservation right now.";
};

const experienceSignals = [
  {
    title: "Expert Keynotes",
    copy: "Hear from global visionaries leading the charge in sustainable tech and architectural design.",
  },
  {
    title: "Curated Networking",
    copy: "Connect with 500+ peers through our AI-driven matching system for meaningful conversations.",
  },
  {
    title: "Immersive Labs",
    copy: "Hands-on deep dives with interactive demos and guided product sessions.",
  },
  {
    title: "Live Installations",
    copy: "Multi-sensory exhibits blending light, motion, and generative storytelling.",
  },
];

const packageIncludes = [
  ["Refreshments", "Full Catering"],
  ["Materials", "Digital Toolkit"],
  ["Entry", "General Admission"],
];

export function ReservationPage() {
  const [searchParams] = useSearchParams();
  const selectedEventSlug =
    (searchParams.get("event") ?? FALLBACK_EVENT_SLUG).trim() ||
    FALLBACK_EVENT_SLUG;

  const [selectedEvent, setSelectedEvent] = useState<PublicEvent | null>(null);
  const [isEventLoading, setEventLoading] = useState(true);
  const [eventLoadError, setEventLoadError] = useState<string | null>(null);
  const [isModalOpen, setModalOpen] = useState(true);
  const [formValues, setFormValues] =
    useState<ReservationFormValues>(initialFormValues);
  const [touchedFields, setTouchedFields] = useState<
    Record<keyof ReservationFormValues, boolean>
  >({
    fullName: false,
    email: false,
    phone: false,
  });
  const [submitAttempted, setSubmitAttempted] = useState(false);
  const [isSubmitting, setSubmitting] = useState(false);
  const [submitErrorMessage, setSubmitErrorMessage] = useState<string | null>(
    null,
  );
  const navigate = useNavigate();

  const formErrors = useMemo(
    () => validateReservationForm(formValues),
    [formValues],
  );
  const isFormValid = Object.keys(formErrors).length === 0;

  const shouldShowFieldError = (field: keyof ReservationFormValues) => {
    if (!submitAttempted && !touchedFields[field]) {
      return false;
    }

    return Boolean(formErrors[field]);
  };

  const handleFieldChange =
    (field: keyof ReservationFormValues) =>
    (event: ChangeEvent<HTMLInputElement>) => {
      const nextValue = event.target.value;
      setFormValues((current) => ({
        ...current,
        [field]: nextValue,
      }));
    };

  const handleFieldBlur = (field: keyof ReservationFormValues) => {
    setTouchedFields((current) => ({
      ...current,
      [field]: true,
    }));
  };

  useEffect(() => {
    if (!isModalOpen) {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape" && !isSubmitting) {
        setModalOpen(false);
      }
    };

    document.body.style.overflow = "hidden";
    window.addEventListener("keydown", handleKeyDown);

    return () => {
      window.removeEventListener("keydown", handleKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [isModalOpen, isSubmitting]);

  useEffect(() => {
    let isMounted = true;

    const loadEvent = async () => {
      setEventLoading(true);
      setEventLoadError(null);

      try {
        const payload = await fetchPublicEventBySlug(selectedEventSlug);

        if (!isMounted) {
          return;
        }

        setSelectedEvent(payload);
      } catch (error) {
        if (!isMounted) {
          return;
        }

        setSelectedEvent(null);

        if (error instanceof Error && error.message === "event_not_found") {
          setEventLoadError("Selected event is not available.");
        } else {
          setEventLoadError("Unable to load selected event details.");
        }
      } finally {
        if (isMounted) {
          setEventLoading(false);
        }
      }
    };

    void loadEvent();

    return () => {
      isMounted = false;
    };
  }, [selectedEventSlug]);

  const handleReservationSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitAttempted(true);
    setSubmitErrorMessage(null);

    if (!isFormValid || !selectedEvent || isEventLoading || eventLoadError) {
      if (!selectedEvent || isEventLoading || eventLoadError) {
        setSubmitErrorMessage("This event cannot be reserved right now.");
      }

      return;
    }

    setSubmitting(true);

    try {
      const payload = await createReservation({
        event_slug: selectedEvent.slug,
        full_name: formValues.fullName.trim(),
        email: formValues.email.trim(),
        phone: formValues.phone.trim(),
      });

      const confirmationState: ReservationConfirmationState = {
        attendeeName: payload.attendee_name,
        attendeeEmail: payload.attendee_email,
        attendeePhone: payload.attendee_phone,
        reservationId: payload.reservation_id,
        eventTitle: payload.event_title,
        date: payload.event_date,
        time: payload.event_time,
        location: payload.event_location,
        qrCodeToken: payload.qr_code_token,
        ticketDownloadUrl: payload.ticket_download_url,
      };

      saveLatestTicket(confirmationState);

      setSubmitting(false);
      setModalOpen(false);
      navigate("/confirmation", {
        state: confirmationState,
      });
    } catch (error) {
      setSubmitting(false);
      setSubmitErrorMessage(mapReservationErrorMessage(error));
    }
  };

  const eventTitle = selectedEvent?.title ?? "Loading selected event";
  const eventSummary =
    selectedEvent?.summary ??
    "The event details are loading from the API. Please wait a moment.";
  const eventCategory = selectedEvent?.category ?? "Exclusive event";
  const eventPrice = selectedEvent ? formatEventPrice(selectedEvent) : "--";
  const eventDate = selectedEvent
    ? buildEventDateLabel(selectedEvent.starts_at)
    : "Date to be announced";
  const eventTime = selectedEvent
    ? buildEventTimeLabel(selectedEvent.starts_at)
    : "Time to be announced";
  const eventLocation = selectedEvent
    ? `${selectedEvent.location}, ${selectedEvent.city}`
    : "Venue details are loading";
  const bookingDisabled = isEventLoading || Boolean(eventLoadError);

  return (
    <>
      <section className="reservation-hero" aria-labelledby="reservation-title">
        <div className="reservation-hero-copy">
          <p className="eyebrow">{eventCategory}</p>
          <h1 id="reservation-title">{eventTitle}</h1>
          <p>{eventSummary}</p>
          {eventLoadError ? (
            <p className="reservation-event-note" role="alert">
              {eventLoadError}
            </p>
          ) : null}
        </div>
      </section>

      <section className="reservation-content">
        <article className="reservation-overview section-block">
          <h2>Experience The Unfolding</h2>
          <p>
            EventFlow is more than just a conference. It is a curated
            architecture journey through the next decade of technology and
            design. Join industry pioneers as we explore the convergence of
            lucid interfaces, human-centric AI, and the evolving landscape of
            digital experiences.
          </p>
          <p>
            The day is structured as a series of immersive chapters, each set in
            a different sensory zone of The Glass Pavilion. From high-energy
            keynote firesides to tranquil deep-dive labs, every moment is
            architected for maximum engagement and professional growth.
          </p>

          <div className="reservation-signal-grid">
            {experienceSignals.map((signal) => (
              <article key={signal.title} className="reservation-signal-card">
                <h3>{signal.title}</h3>
                <p>{signal.copy}</p>
              </article>
            ))}
          </div>
        </article>

        <aside className="reservation-price-card" aria-label="Ticket package">
          <p className="reservation-price-label">Admission</p>
          <strong>{eventPrice}</strong>
          <ul>
            {packageIncludes.map(([label, value]) => (
              <li key={label}>
                <span>{label}</span>
                <p>{value}</p>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="button-primary wide"
            disabled={bookingDisabled}
            onClick={() => {
              if (bookingDisabled) {
                return;
              }

              setModalOpen(true);
              setSubmitAttempted(false);
              setSubmitErrorMessage(null);
            }}
          >
            Book now
          </button>
          <small>
            {eventLoadError ?? "Non-refundable. Limited tickets remaining."}
          </small>
        </aside>
      </section>

      <section
        className="reservation-location section-block"
        aria-label="Venue details"
      >
        <div>
          <h2>Venue & Direction</h2>
          <h3>{selectedEvent?.location ?? "Loading venue"}</h3>
          <p>{eventLocation}</p>
          <p>
            {eventDate} at {eventTime}
          </p>
        </div>
        <div
          className="reservation-map"
          role="img"
          aria-label="Map preview placeholder"
        />
      </section>

      {isModalOpen ? (
        <div className="reservation-modal-layer" role="presentation">
          <button
            type="button"
            className="reservation-modal-backdrop"
            onClick={() => {
              if (!isSubmitting) {
                setModalOpen(false);
              }
            }}
            aria-label="Close reservation dialog"
            tabIndex={-1}
            disabled={isSubmitting}
          />
          <section
            className="reservation-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reservation-modal-title"
            aria-describedby="reservation-modal-description"
          >
            <button
              type="button"
              className="reservation-modal-close"
              onClick={() => setModalOpen(false)}
              aria-label="Close"
              disabled={isSubmitting}
            >
              x
            </button>

            <h2 id="reservation-modal-title">Secure Your Spot</h2>
            <p id="reservation-modal-description">
              Fill in your details to finalize the reservation for {eventTitle}.
            </p>

            <form onSubmit={handleReservationSubmit}>
              <label htmlFor="reservation-name">Full Name</label>
              <input
                id="reservation-name"
                type="text"
                placeholder="John Architect"
                autoComplete="name"
                required
                autoFocus
                value={formValues.fullName}
                onChange={handleFieldChange("fullName")}
                onBlur={() => handleFieldBlur("fullName")}
                aria-invalid={shouldShowFieldError("fullName")}
                aria-describedby={
                  shouldShowFieldError("fullName")
                    ? "reservation-name-error"
                    : undefined
                }
              />
              {shouldShowFieldError("fullName") ? (
                <span
                  id="reservation-name-error"
                  className="reservation-field-error"
                  role="alert"
                >
                  {formErrors.fullName}
                </span>
              ) : null}

              <label htmlFor="reservation-email">Email Address</label>
              <input
                id="reservation-email"
                type="email"
                placeholder="john@auraevents.com"
                autoComplete="email"
                required
                value={formValues.email}
                onChange={handleFieldChange("email")}
                onBlur={() => handleFieldBlur("email")}
                aria-invalid={shouldShowFieldError("email")}
                aria-describedby={
                  shouldShowFieldError("email")
                    ? "reservation-email-error"
                    : undefined
                }
              />
              {shouldShowFieldError("email") ? (
                <span
                  id="reservation-email-error"
                  className="reservation-field-error"
                  role="alert"
                >
                  {formErrors.email}
                </span>
              ) : null}

              <label htmlFor="reservation-phone">Phone Number</label>
              <input
                id="reservation-phone"
                type="tel"
                placeholder="+1 (555) 000-0000"
                autoComplete="tel"
                required
                value={formValues.phone}
                onChange={handleFieldChange("phone")}
                onBlur={() => handleFieldBlur("phone")}
                aria-invalid={shouldShowFieldError("phone")}
                aria-describedby={
                  shouldShowFieldError("phone")
                    ? "reservation-phone-error"
                    : undefined
                }
              />
              {shouldShowFieldError("phone") ? (
                <span
                  id="reservation-phone-error"
                  className="reservation-field-error"
                  role="alert"
                >
                  {formErrors.phone}
                </span>
              ) : null}

              {submitErrorMessage ? (
                <p className="reservation-submit-error" role="alert">
                  {submitErrorMessage}
                </p>
              ) : null}

              <button
                type="submit"
                className="button-primary wide"
                disabled={isSubmitting || bookingDisabled}
              >
                {isSubmitting
                  ? "Processing..."
                  : bookingDisabled
                    ? "Event unavailable"
                    : "Confirm Reservation"}
              </button>
            </form>

            <small className="reservation-submit-note" aria-live="polite">
              {isSubmitting
                ? "Processing your reservation and preparing your pass..."
                : "Secure payment processed by Aura Events"}
            </small>
          </section>
        </div>
      ) : null}
    </>
  );
}
