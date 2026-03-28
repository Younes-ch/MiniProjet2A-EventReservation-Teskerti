import { createBrowserRouter } from "react-router-dom";
import { AdminPage } from "./pages/AdminPage";
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
]);
