import { createBrowserRouter } from "react-router-dom";
import { AdminPage } from "./pages/AdminPage";
import { ConfirmationPage } from "./pages/ConfirmationPage";
import { EventDetailPage } from "./pages/EventDetailPage";
import { ForgotPasswordPage } from "./pages/ForgotPasswordPage";
import { HomePage } from "./pages/HomePage";
import { LoginPage } from "./pages/LoginPage";
import { ProfilePage } from "./pages/ProfilePage";
import { ReservationPage } from "./pages/ReservationPage";
import { SchedulesPage } from "./pages/SchedulesPage";
import { SignupPage } from "./pages/SignupPage";
import { TicketsPage } from "./pages/TicketsPage";
import { VenuesPage } from "./pages/VenuesPage";
import { AppLayout } from "./shell/AppLayout";

export const router = createBrowserRouter([
  {
    path: "/",
    element: <AppLayout />,
    children: [
      {
        index: true,
        element: <HomePage />,
      },
      {
        path: "events/:eventSlug",
        element: <EventDetailPage />,
      },
      {
        path: "login",
        element: <LoginPage />,
      },
      {
        path: "signup",
        element: <SignupPage />,
      },
      {
        path: "forgot-password",
        element: <ForgotPasswordPage />,
      },
      {
        path: "admin",
        element: <AdminPage />,
      },
      {
        path: "profile",
        element: <ProfilePage />,
      },
      {
        path: "tickets",
        element: <TicketsPage />,
      },
      {
        path: "venues",
        element: <VenuesPage />,
      },
      {
        path: "schedules",
        element: <SchedulesPage />,
      },
      {
        path: "reserve",
        element: <ReservationPage />,
      },
    ],
  },
  {
    path: "/confirmation",
    element: <ConfirmationPage />,
  },
]);
