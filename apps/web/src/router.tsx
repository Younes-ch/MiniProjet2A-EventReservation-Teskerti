import { createBrowserRouter } from "react-router-dom";
import { AdminPage } from "./pages/AdminPage";
import { ConfirmationPage } from "./pages/ConfirmationPage";
import { EventDetailPage } from "./pages/EventDetailPage";
import { HomePage } from "./pages/HomePage";
import { LoginPage } from "./pages/LoginPage";
import { ReservationPage } from "./pages/ReservationPage";
import { TicketsPage } from "./pages/TicketsPage";
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
        path: "admin",
        element: <AdminPage />,
      },
      {
        path: "tickets",
        element: <TicketsPage />,
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
