import {
  type CSSProperties,
  type ChangeEvent,
  type FormEvent,
  useEffect,
  useMemo,
  useState,
} from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import {
  fetchPublicEventBySlug,
  fetchPublicEventSeatMap,
  type PublicEvent,
  type PublicEventSeatMap,
} from "../lib/eventsClient";
import {
  buildVenueMapDirectionsUrl,
  buildVenueMapEmbedUrl,
} from "../lib/mapLinks";
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
  seatLabels: string[];
  qrCodeToken: string;
  ticketDownloadUrl: string;
};

const FALLBACK_EVENT_SLUG = "midnight-resonance-2-0";
const MAX_SEAT_SELECTION = 4;

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

  if (error.message === "seat_selection_required") {
    return "Please select at least one seat before confirming.";
  }

  if (error.message === "seat_selection_invalid") {
    return "One or more selected seats are invalid. Please choose seats again.";
  }

  if (error.message === "seat_selection_too_large") {
    return `You can reserve up to ${MAX_SEAT_SELECTION} seats at once.`;
  }

  if (error.message === "seats_unavailable") {
    return "Some selected seats are no longer available. Pick different seats and retry.";
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
  const [seatMap, setSeatMap] = useState<PublicEventSeatMap | null>(null);
  const [isSeatMapLoading, setSeatMapLoading] = useState(true);
  const [isSeatMapRefreshing, setSeatMapRefreshing] = useState(false);
  const [seatMapError, setSeatMapError] = useState<string | null>(null);
  const [lastSeatMapSyncedAt, setLastSeatMapSyncedAt] = useState<string | null>(
    null,
  );
  const [selectedSeatLabels, setSelectedSeatLabels] = useState<string[]>([]);
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

  const seatStatusByLabel = useMemo(() => {
    const entries =
      seatMap?.items.map((item): [string, "available" | "reserved"] => [
        item.label,
        item.status,
      ]) ?? [];
    return new Map(entries);
  }, [seatMap]);

  const selectedSeatSet = useMemo(
    () => new Set(selectedSeatLabels),
    [selectedSeatLabels],
  );

  const availableSeatCount = useMemo(() => {
    if (!seatMap) {
      return 0;
    }

    return seatMap.items.filter((item) => item.status === "available").length;
  }, [seatMap]);

  const seatMapSyncedTimeLabel = useMemo(() => {
    if (!lastSeatMapSyncedAt) {
      return null;
    }

    const parsed = new Date(lastSeatMapSyncedAt);
    if (Number.isNaN(parsed.getTime())) {
      return null;
    }

    return parsed.toLocaleTimeString("en-US", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: false,
    });
  }, [lastSeatMapSyncedAt]);

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

  const handleSeatToggle = (seatLabel: string) => {
    const seatStatus = seatStatusByLabel.get(seatLabel);
    if (seatStatus !== "available" || isSubmitting) {
      return;
    }

    if (
      !selectedSeatSet.has(seatLabel) &&
      selectedSeatLabels.length >= MAX_SEAT_SELECTION
    ) {
      setSubmitErrorMessage(
        `You can reserve up to ${MAX_SEAT_SELECTION} seats at once.`,
      );
      return;
    }

    setSubmitErrorMessage(null);
    setSelectedSeatLabels((current) =>
      current.includes(seatLabel)
        ? current.filter((label) => label !== seatLabel)
        : [...current, seatLabel],
    );
  };

  const handleSeatSelectionReset = () => {
    if (!seatMap || isSubmitting) {
      return;
    }

    const firstAvailableSeat = seatMap.items.find(
      (item) => item.status === "available",
    );

    setSelectedSeatLabels(firstAvailableSeat ? [firstAvailableSeat.label] : []);
    setSubmitErrorMessage(null);
  };

  const handleSeatSelectionAutoFill = () => {
    if (!seatMap || isSubmitting) {
      return;
    }

    const nextSelection = seatMap.items
      .filter((item) => item.status === "available")
      .slice(0, MAX_SEAT_SELECTION)
      .map((item) => item.label);

    setSelectedSeatLabels(nextSelection);
    setSubmitErrorMessage(null);
  };

  const refreshSeatMap = async () => {
    if (
      !selectedEvent ||
      isSubmitting ||
      isEventLoading ||
      isSeatMapLoading ||
      isSeatMapRefreshing
    ) {
      return;
    }

    setSeatMapRefreshing(true);
    setSeatMapError(null);

    try {
      const seatMapPayload = await fetchPublicEventSeatMap(selectedEvent.slug);
      const availableSeatSet = new Set(
        seatMapPayload.items
          .filter((item) => item.status === "available")
          .map((item) => item.label),
      );

      setSeatMap(seatMapPayload);
      setSelectedSeatLabels((current) => {
        const remainingSelected = current
          .filter((seatLabel) => availableSeatSet.has(seatLabel))
          .slice(0, MAX_SEAT_SELECTION);

        if (remainingSelected.length > 0) {
          return remainingSelected;
        }

        const firstAvailableSeat = seatMapPayload.items.find(
          (item) => item.status === "available",
        );

        return firstAvailableSeat ? [firstAvailableSeat.label] : [];
      });

      setLastSeatMapSyncedAt(new Date().toISOString());
      setSubmitErrorMessage(null);
    } catch {
      setSeatMapError("Unable to refresh seat map right now.");
    } finally {
      setSeatMapRefreshing(false);
    }
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
      setSeatMapLoading(true);
      setEventLoadError(null);
      setSeatMapError(null);
      setLastSeatMapSyncedAt(null);
      setSelectedSeatLabels([]);

      try {
        const [eventPayload, seatMapPayload] = await Promise.all([
          fetchPublicEventBySlug(selectedEventSlug),
          fetchPublicEventSeatMap(selectedEventSlug),
        ]);

        if (!isMounted) {
          return;
        }

        setSelectedEvent(eventPayload);
        setSeatMap(seatMapPayload);
        setLastSeatMapSyncedAt(new Date().toISOString());

        const firstAvailableSeat = seatMapPayload.items.find(
          (item) => item.status === "available",
        );
        setSelectedSeatLabels(
          firstAvailableSeat ? [firstAvailableSeat.label] : [],
        );
      } catch (error) {
        if (!isMounted) {
          return;
        }

        setSelectedEvent(null);
        setSeatMap(null);
        setLastSeatMapSyncedAt(null);
        setSelectedSeatLabels([]);

        if (error instanceof Error && error.message === "event_not_found") {
          setEventLoadError("Selected event is not available.");
          setSeatMapError("Seat map is unavailable for this event.");
        } else {
          setEventLoadError("Unable to load selected event details.");
          setSeatMapError("Unable to load seat map right now.");
        }
      } finally {
        if (isMounted) {
          setEventLoading(false);
          setSeatMapLoading(false);
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

    if (
      !isFormValid ||
      !selectedEvent ||
      isEventLoading ||
      eventLoadError ||
      isSeatMapLoading ||
      seatMapError ||
      isSeatMapRefreshing
    ) {
      if (
        !selectedEvent ||
        isEventLoading ||
        eventLoadError ||
        isSeatMapLoading ||
        seatMapError ||
        isSeatMapRefreshing
      ) {
        setSubmitErrorMessage("This event cannot be reserved right now.");
      }

      return;
    }

    if (selectedSeatLabels.length === 0) {
      setSubmitErrorMessage("Select at least one seat to continue.");
      return;
    }

    const hasUnavailableSelectedSeat = selectedSeatLabels.some(
      (seatLabel) => seatStatusByLabel.get(seatLabel) !== "available",
    );
    if (hasUnavailableSelectedSeat) {
      setSubmitErrorMessage(
        "One or more selected seats are no longer available. Choose seats again.",
      );
      return;
    }

    setSubmitting(true);

    try {
      const payload = await createReservation({
        event_slug: selectedEvent.slug,
        full_name: formValues.fullName.trim(),
        email: formValues.email.trim(),
        phone: formValues.phone.trim(),
        seat_labels: selectedSeatLabels,
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
        seatLabels: payload.seat_labels,
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
  const venueMapEmbedUrl = selectedEvent
    ? buildVenueMapEmbedUrl(selectedEvent.location, selectedEvent.city)
    : "";
  const venueMapDirectionsUrl = selectedEvent
    ? buildVenueMapDirectionsUrl(selectedEvent.location, selectedEvent.city)
    : "";
  const bookingDisabled =
    isEventLoading ||
    Boolean(eventLoadError) ||
    isSeatMapLoading ||
    isSeatMapRefreshing ||
    Boolean(seatMapError) ||
    availableSeatCount <= 0;

  const hasSeatSelection = selectedSeatLabels.length > 0;
  const isSeatSelectionAtLimit =
    selectedSeatLabels.length >= MAX_SEAT_SELECTION;

  const seatGridStyles: CSSProperties | undefined = seatMap
    ? {
        gridTemplateColumns: `repeat(${seatMap.layout.columns}, minmax(42px, 1fr))`,
      }
    : undefined;

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
            {eventLoadError ??
              seatMapError ??
              (!isSeatMapLoading && availableSeatCount <= 0
                ? "No seats are currently available for this event."
                : "Non-refundable. Limited tickets remaining.")}
          </small>
        </aside>
      </section>

      <section
        className="reservation-seat-map section-block"
        aria-labelledby="seat-map-title"
      >
        <header className="reservation-seat-map-header">
          <div>
            <h2 id="seat-map-title">Choose your seats</h2>
            <p>
              Pick up to {MAX_SEAT_SELECTION} seats. Availability is synced with
              recent reservations.
            </p>
          </div>
          <div className="reservation-seat-counters" aria-live="polite">
            <div className="reservation-seat-counter">
              <strong>{selectedSeatLabels.length}</strong>
              <span>selected</span>
            </div>
            <div className="reservation-seat-counter reservation-seat-counter-secondary">
              <strong>{availableSeatCount}</strong>
              <span>available</span>
            </div>
          </div>
        </header>

        {isSeatMapLoading ? (
          <p className="home-api-state" role="status">
            Loading seat map...
          </p>
        ) : null}

        {seatMapError ? (
          <p className="home-api-state home-api-state-error" role="alert">
            {seatMapError}
          </p>
        ) : null}

        {seatMap ? (
          <>
            <div
              className="reservation-seat-legend"
              aria-label="Seat status legend"
            >
              <span>
                <i
                  className="reservation-seat-dot reservation-seat-dot-available"
                  aria-hidden="true"
                />
                Available
              </span>
              <span>
                <i
                  className="reservation-seat-dot reservation-seat-dot-selected"
                  aria-hidden="true"
                />
                Selected
              </span>
              <span>
                <i
                  className="reservation-seat-dot reservation-seat-dot-reserved"
                  aria-hidden="true"
                />
                Reserved
              </span>
            </div>

            <div
              className="reservation-seat-toolbar"
              role="group"
              aria-label="Seat map actions"
            >
              <button
                type="button"
                className="reservation-seat-tool-button"
                disabled={
                  isSubmitting ||
                  isSeatMapLoading ||
                  isSeatMapRefreshing ||
                  !selectedEvent
                }
                onClick={() => {
                  void refreshSeatMap();
                }}
              >
                {isSeatMapRefreshing ? "Refreshing..." : "Refresh availability"}
              </button>

              <button
                type="button"
                className="reservation-seat-tool-button reservation-seat-tool-button-subtle"
                disabled={
                  isSubmitting || isSeatMapLoading || availableSeatCount === 0
                }
                onClick={handleSeatSelectionAutoFill}
              >
                Select best {MAX_SEAT_SELECTION}
              </button>

              <button
                type="button"
                className="reservation-seat-tool-button reservation-seat-tool-button-subtle"
                disabled={
                  isSubmitting || isSeatMapLoading || availableSeatCount === 0
                }
                onClick={handleSeatSelectionReset}
              >
                Reset selection
              </button>
            </div>

            {seatMapSyncedTimeLabel ? (
              <p className="reservation-seat-sync-note" aria-live="polite">
                Availability synced at {seatMapSyncedTimeLabel}
              </p>
            ) : null}

            <div className="reservation-seat-grid-shell">
              <p className="reservation-seat-stage" aria-hidden="true">
                Stage
              </p>

              <div
                className="reservation-seat-grid"
                style={seatGridStyles}
                role="grid"
                aria-label="Seat selection grid"
              >
                {seatMap.items.map((seat) => {
                  const isReserved = seat.status === "reserved";
                  const isSelected = selectedSeatSet.has(seat.label);
                  const isLockedByLimit =
                    isSeatSelectionAtLimit && !isSelected && !isReserved;

                  const seatStateClass = isReserved
                    ? "reservation-seat-button-reserved"
                    : isSelected
                      ? "reservation-seat-button-selected"
                      : isLockedByLimit
                        ? "reservation-seat-button-locked"
                        : "reservation-seat-button-available";

                  const seatStateLabel = isReserved
                    ? "reserved"
                    : isSelected
                      ? "selected"
                      : isLockedByLimit
                        ? "temporarily locked"
                        : "available";

                  return (
                    <button
                      key={seat.label}
                      type="button"
                      className={`reservation-seat-button ${seatStateClass}`}
                      disabled={isReserved || isLockedByLimit || isSubmitting}
                      aria-pressed={isSelected}
                      aria-label={`${seat.label} ${seatStateLabel}`}
                      aria-describedby="reservation-seat-summary"
                      onClick={() => handleSeatToggle(seat.label)}
                    >
                      {seat.label}
                    </button>
                  );
                })}
              </div>
            </div>

            <p
              id="reservation-seat-summary"
              className="reservation-seat-summary"
              aria-live="polite"
            >
              {hasSeatSelection
                ? `Selected seats: ${selectedSeatLabels.join(", ")}`
                : "No seats selected yet."}
            </p>

            <p className="reservation-seat-helper" aria-live="polite">
              {isSeatSelectionAtLimit
                ? `Maximum of ${MAX_SEAT_SELECTION} seats selected. Deselect one to choose another.`
                : `You can select ${MAX_SEAT_SELECTION - selectedSeatLabels.length} more seat${
                    MAX_SEAT_SELECTION - selectedSeatLabels.length === 1
                      ? ""
                      : "s"
                  }.`}
            </p>
          </>
        ) : null}
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
          {selectedEvent ? (
            <a
              className="button-secondary reservation-location-link"
              href={venueMapDirectionsUrl}
              target="_blank"
              rel="noreferrer"
            >
              Open directions
            </a>
          ) : null}
        </div>
        {selectedEvent ? (
          <div className="reservation-map-shell">
            <iframe
              className="reservation-map-frame"
              title={`Venue map for ${selectedEvent.title}`}
              src={venueMapEmbedUrl}
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
            />
          </div>
        ) : (
          <div
            className="reservation-map"
            role="img"
            aria-label="Map preview placeholder"
          />
        )}
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

              <p className="reservation-seat-modal-summary">
                Seats:{" "}
                {selectedSeatLabels.length > 0
                  ? selectedSeatLabels.join(", ")
                  : "Not selected"}
              </p>

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
