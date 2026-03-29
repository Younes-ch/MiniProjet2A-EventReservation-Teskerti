import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  fetchPasskeyRegistrationOptions,
  fetchAuthenticatedUser,
  fetchPasskeyCredentials,
  renamePasskeyCredential,
  revokePasskeyCredential,
  verifyPasskeyRegistration,
  type AuthProfile,
  type PasskeyCredentialItem,
} from "../lib/authClient";
import { loadAuthSession } from "../lib/authStorage";
import {
  base64UrlToBuffer,
  preparePasskeyClientData,
} from "../lib/webauthnUtils";

type DashboardState = "loading" | "ready" | "unauthenticated" | "error";

export function ProfilePage() {
  const [dashboardState, setDashboardState] = useState<DashboardState>("loading");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [authUser, setAuthUser] = useState<AuthProfile | null>(null);
  const [passkeyCredentials, setPasskeyCredentials] = useState<PasskeyCredentialItem[]>([]);
  const [credentialLabelDrafts, setCredentialLabelDrafts] = useState<Record<string, string>>({});
  const [credentialActionId, setCredentialActionId] = useState<string | null>(null);
  const [reloadNonce, setReloadNonce] = useState(0);
  const [actionErrorMessage, setActionErrorMessage] = useState<string | null>(null);
  const [actionSuccessMessage, setActionSuccessMessage] = useState<string | null>(null);
  const [isActionSubmitting, setActionSubmitting] = useState(false);
  const [accessToken, setAccessToken] = useState<string | null>(null);

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

      let userProfile: AuthProfile | null = null;
      const validAccessToken = session.accessToken;

      try {
        userProfile = await fetchAuthenticatedUser(validAccessToken);
      } catch (error) {
        if (isMounted) {
          setDashboardState("unauthenticated");
        }
        return;
      }

      try {
        const passkeyCredentialsPayload = await fetchPasskeyCredentials(validAccessToken);

        if (!isMounted) return;

        setAuthUser(userProfile);
        setPasskeyCredentials(passkeyCredentialsPayload.items);
        setCredentialLabelDrafts(
          passkeyCredentialsPayload.items.reduce<Record<string, string>>((accumulator, credential) => {
            accumulator[credential.id] = credential.label;
            return accumulator;
          }, {}),
        );
        setAccessToken(validAccessToken);
        setActionErrorMessage(null);
        setActionSuccessMessage(null);
        setDashboardState("ready");
      } catch (error) {
        if (!isMounted) return;
        setErrorMessage("Unable to load profile data right now.");
        setDashboardState("error");
      }
    };

    void loadDashboard();

    return () => {
      isMounted = false;
    };
  }, [reloadNonce]);

  const mapCrudErrorMessage = (error: unknown): string => {
    if (error instanceof Error) {
      return error.message;
    }
    return "An error occurred.";
  };

  const handlePasskeyEnrollment = async () => {
    if (!accessToken) return;
    setActionSubmitting(true);
    setActionErrorMessage(null);
    setActionSuccessMessage(null);

    try {
      const registrationOptions = await fetchPasskeyRegistrationOptions(accessToken, "My Passkey");

      const publicKeyCredentialCreationOptions: PublicKeyCredentialCreationOptions = {
        challenge: base64UrlToBuffer(registrationOptions.challenge),
        rp: {
          name: "Teskerti",
          id: registrationOptions.rp_id,
        },
        user: {
          id: base64UrlToBuffer(btoa(registrationOptions.user.email).replace(/=/g, "")),
          name: registrationOptions.user.email,
          displayName: registrationOptions.user.display_name,
        },
        pubKeyCredParams: [
          { alg: -7, type: "public-key" },
          { alg: -257, type: "public-key" }
        ],
        authenticatorSelection: {
          userVerification: "preferred"
        },
        timeout: registrationOptions.timeout,
        attestation: "none",
      };

      const credential = await navigator.credentials.create({
        publicKey: publicKeyCredentialCreationOptions,
      }) as PublicKeyCredential;

      if (!credential) {
        throw new Error("Passkey creation cancelled or failed.");
      }

      const generatedCredentialId = credential.id;

      const registrationResult = await verifyPasskeyRegistration(accessToken, {
        challenge: registrationOptions.challenge,
        credential_id: generatedCredentialId,
        label: "My Passkey",
        client_data: preparePasskeyClientData(
          "webauthn.create",
          registrationOptions.challenge,
        ),
      });

      setActionSuccessMessage(
        `Passkey enrolled. Total credentials: ${registrationResult.total_credentials}.`,
      );
      setReloadNonce((previous) => previous + 1);
    } catch (error) {
      setActionErrorMessage(mapCrudErrorMessage(error));
    } finally {
      setActionSubmitting(false);
    }
  };

  const handleCredentialLabelChange = (credentialId: string, nextLabel: string) => {
    setCredentialLabelDrafts((current) => ({
      ...current,
      [credentialId]: nextLabel,
    }));
  };

  const handleRenameCredential = async (credentialId: string) => {
    if (!accessToken) return;
    const nextLabel = (credentialLabelDrafts[credentialId] ?? "").trim();
    if (nextLabel.length === 0 || nextLabel.length > 80) {
      setActionErrorMessage("Passkey label must be between 1 and 80 characters.");
      return;
    }

    setCredentialActionId(credentialId);
    setActionErrorMessage(null);
    setActionSuccessMessage(null);

    try {
      const updatedCredential = await renamePasskeyCredential(accessToken, credentialId, nextLabel);

      setPasskeyCredentials((current) =>
        current.map((credential) =>
          credential.id === credentialId
            ? { ...credential, label: updatedCredential.label }
            : credential,
        ),
      );
      setCredentialLabelDrafts((current) => ({
        ...current,
        [credentialId]: updatedCredential.label,
      }));
      setActionSuccessMessage("Passkey label updated.");
    } catch (error) {
      setActionErrorMessage(mapCrudErrorMessage(error));
    } finally {
      setCredentialActionId(null);
    }
  };

  const handleRevokeCredential = async (credentialId: string) => {
    if (!accessToken) return;
    if (!window.confirm("Revoke this passkey credential?")) {
      return;
    }

    setCredentialActionId(credentialId);
    setActionErrorMessage(null);
    setActionSuccessMessage(null);

    try {
      await revokePasskeyCredential(accessToken, credentialId);

      setPasskeyCredentials((current) =>
        current.filter((credential) => credential.id !== credentialId),
      );
      setCredentialLabelDrafts((current) => {
        const next = { ...current };
        delete next[credentialId];
        return next;
      });
      setActionSuccessMessage("Passkey credential revoked.");
    } catch (error) {
      setActionErrorMessage(mapCrudErrorMessage(error));
    } finally {
      setCredentialActionId(null);
    }
  };

  if (dashboardState === "loading") {
    return (
      <section className="section-block admin-shell">
        <p className="home-api-state" role="status">
          Loading profile...
        </p>
      </section>
    );
  }

  if (dashboardState === "unauthenticated" || dashboardState === "error") {
    return (
      <section className="section-block admin-shell">
        <p className="home-api-state home-api-state-error" role="alert">
          {errorMessage ?? "Please sign in to access your profile."}
        </p>
        <div className="admin-state-actions">
          <Link to="/login" className="button-primary">Go to login</Link>
          <Link to="/" className="button-secondary">Back to events</Link>
        </div>
      </section>
    );
  }

  const displayName = authUser?.display_name ?? "User";

  return (
    <section className="section-block admin-shell">
      <header className="admin-shell-header">
        <div>
          <p className="eyebrow">User Profile</p>
          <h1>{displayName}</h1>
          <p className="section-copy">{authUser?.email}</p>
        </div>
        <div className="admin-search-row">
          <button
            type="button"
            className="button-secondary"
            onClick={() => void handlePasskeyEnrollment()}
            disabled={isActionSubmitting}
          >
            Enroll Passkey
          </button>
        </div>
      </header>

      {actionErrorMessage ? (
        <p className="home-api-state home-api-state-error" role="alert">
          {actionErrorMessage}
        </p>
      ) : null}

      {actionSuccessMessage ? (
        <p className="home-api-state home-api-state-success" role="status">
          {actionSuccessMessage}
        </p>
      ) : null}

      <div className="admin-main-grid" style={{ gridTemplateColumns: 'minmax(0, 600px)' }}>
          <article className="admin-passkey-card">
            <header className="admin-passkey-head">
              <h2>Passkey Security</h2>
              <small>{passkeyCredentials.length} credentials</small>
            </header>

            <p className="admin-passkey-copy">
              Current session: {authUser?.auth_method ?? "unknown"}.
            </p>

            {passkeyCredentials.length > 0 ? (
              <ul className="admin-passkey-credential-list">
                {passkeyCredentials.map((credential) => (
                  <li key={credential.id} className="admin-passkey-credential-item">
                    <label>
                      Label
                      <input
                        type="text"
                        value={credentialLabelDrafts[credential.id] ?? credential.label}
                        maxLength={80}
                        onChange={(event) =>
                          handleCredentialLabelChange(credential.id, event.target.value)
                        }
                      />
                    </label>
                    <small>{credential.id}</small>
                    <div className="admin-row-actions">
                      <button
                        type="button"
                        className="admin-row-action-button"
                        onClick={() => void handleRenameCredential(credential.id)}
                        disabled={credentialActionId === credential.id}
                      >
                        {credentialActionId === credential.id ? "Saving..." : "Save label"}
                      </button>
                      <button
                        type="button"
                        className="admin-row-action-button admin-row-action-danger"
                        onClick={() => void handleRevokeCredential(credential.id)}
                        disabled={credentialActionId === credential.id}
                      >
                        Revoke
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="home-api-state">
                No passkeys registered yet. Use Enroll Passkey to add one.
              </p>
            )}
          </article>
      </div>
    </section>
  );
}
