const buildVenueQuery = (location: string, city: string): string => {
  const segments = [location.trim(), city.trim()].filter((segment) => segment.length > 0);

  if (segments.length === 0) {
    return "Morocco";
  }

  return segments.join(", ");
};

export const buildVenueMapEmbedUrl = (location: string, city: string): string => {
  const query = encodeURIComponent(buildVenueQuery(location, city));
  return `https://www.google.com/maps?q=${query}&output=embed`;
};

export const buildVenueMapDirectionsUrl = (location: string, city: string): string => {
  const query = encodeURIComponent(buildVenueQuery(location, city));
  return `https://www.google.com/maps/search/?api=1&query=${query}`;
};
