import { type FormEvent, useState } from "react";
import { Link } from "react-router-dom";
import { requestPasswordReset } from "../lib/authClient";

const mapForgotPasswordError = (error: unknown): string => {
  if (!(error instanceof Error)) {
    return "Unable to submit reset request right now.";
  }

  if (error.message === "email_required" || error.message === "invalid_email") {
    return "Please provide a valid email address.";
  }

  return "Unable to submit reset request right now.";
};

export function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [isSubmitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const normalizedEmail = email.trim().toLowerCase();

    setSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    try {
      await requestPasswordReset(normalizedEmail);
      setSuccessMessage(
        "If this email exists, reset instructions have been sent.",
      );
    } catch (error) {
      setErrorMessage(mapForgotPasswordError(error));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="auth-shell auth-shell-login">
      <article className="auth-card auth-card-login">
        <h2>Forgot password</h2>
        <p className="auth-card-sub">
          Enter your email and we will send reset instructions.
        </p>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label htmlFor="forgot-email">Email address</label>
          <input
            id="forgot-email"
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />

          <button
            type="submit"
            className="button-primary wide"
            disabled={isSubmitting}
          >
            {isSubmitting ? "Submitting..." : "Send reset instructions"}
          </button>
        </form>

        {errorMessage ? (
          <p className="auth-form-status auth-form-status-error" role="alert">
            {errorMessage}
          </p>
        ) : null}

        {successMessage ? (
          <p
            className="auth-form-status auth-form-status-success"
            role="status"
          >
            {successMessage}
          </p>
        ) : null}

        <p className="auth-card-footer">
          Back to <Link to="/login">sign in</Link>
        </p>
      </article>
    </section>
  );
}
