import {
  type ChangeEvent,
  type FormEvent,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { useNavigate } from "react-router-dom";

type ReservationFormValues = {
  fullName: string;
  email: string;
  phone: string;
};

type ReservationFormErrors = Partial<Record<keyof ReservationFormValues, string>>;

type ReservationConfirmationState = {
  attendeeName: string;
  attendeeEmail: string;
  attendeePhone: string;
  reservationId: string;
  eventTitle: string;
  date: string;
  time: string;
  location: string;
};

const initialFormValues: ReservationFormValues = {
  fullName: "",
  email: "",
  phone: "",
};

const confirmationDefaults = {
  eventTitle: "The Luminosity Gala 2024",
  date: "October 24, 2024",
  time: "19:00 - 23:00",
  location: "Grand Plaza, NY",
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

const createReservationId = () => {
  const stamp = Date.now().toString(36).slice(-4).toUpperCase();
  const entropy = Math.random().toString(36).slice(2, 6).toUpperCase();
  return `EF-${stamp}-${entropy}`;
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
  const [isModalOpen, setModalOpen] = useState(true);
  const [formValues, setFormValues] = useState<ReservationFormValues>(initialFormValues);
  const [touchedFields, setTouchedFields] =
    useState<Record<keyof ReservationFormValues, boolean>>({
      fullName: false,
      email: false,
      phone: false,
    });
  const [submitAttempted, setSubmitAttempted] = useState(false);
  const [isSubmitting, setSubmitting] = useState(false);
  const submitTimeoutRef = useRef<number | null>(null);
  const navigate = useNavigate();

  const formErrors = useMemo(() => validateReservationForm(formValues), [formValues]);
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
    return () => {
      if (submitTimeoutRef.current !== null) {
        window.clearTimeout(submitTimeoutRef.current);
      }
    };
  }, []);

  const handleReservationSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitAttempted(true);

    if (!isFormValid) {
      return;
    }

    setSubmitting(true);

    const confirmationState: ReservationConfirmationState = {
      attendeeName: formValues.fullName.trim(),
      attendeeEmail: formValues.email.trim(),
      attendeePhone: formValues.phone.trim(),
      reservationId: createReservationId(),
      eventTitle: confirmationDefaults.eventTitle,
      date: confirmationDefaults.date,
      time: confirmationDefaults.time,
      location: confirmationDefaults.location,
    };

    submitTimeoutRef.current = window.setTimeout(() => {
      setSubmitting(false);
      setModalOpen(false);
      navigate("/confirmation", {
        state: confirmationState,
      });
    }, 900);
  };

  return (
    <>
      <section className="reservation-hero" aria-labelledby="reservation-title">
        <div className="reservation-hero-copy">
          <p className="eyebrow">Exclusive event</p>
          <h1 id="reservation-title">
            EventFlow of Dynamic Interactive Experiences
          </h1>
          <p>
            Join a full-day architecture and innovation summit crafted for
            builders, creators, and operators shaping the next decade.
          </p>
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
          <strong>$299.00</strong>
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
            onClick={() => {
              setModalOpen(true);
              setSubmitAttempted(false);
            }}
          >
            Book now
          </button>
          <small>Non-refundable. Limited tickets remaining.</small>
        </aside>
      </section>

      <section
        className="reservation-location section-block"
        aria-label="Venue details"
      >
        <div>
          <h2>Venue & Direction</h2>
          <h3>The Glass Pavilion</h3>
          <p>42nd High Line St, Manhattan, NY 10011</p>
          <p>Accessible via A, C, E subway lines at 14th St Station.</p>
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
              Fill in your details to finalize the reservation for EventFlow.
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
                <span id="reservation-name-error" className="reservation-field-error" role="alert">
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
                <span id="reservation-email-error" className="reservation-field-error" role="alert">
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
                <span id="reservation-phone-error" className="reservation-field-error" role="alert">
                  {formErrors.phone}
                </span>
              ) : null}

              <button
                type="submit"
                className="button-primary wide"
                disabled={isSubmitting}
              >
                {isSubmitting ? "Processing..." : "Confirm Reservation"}
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
