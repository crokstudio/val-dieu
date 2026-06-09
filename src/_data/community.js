import { fetchCraftEntry } from "../utils/craft-api.js";

const FALLBACK_NEWS_TEXT =
  "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.";

const FALLBACK_COMMUNITY = {
  agenda: [
    {
      day: "Lundi",
      subtitle: "",
      slots: [
        { time: "8h", title: "Laudes" },
        { time: "18h-18h40", title: "Vêpres + Eucharistie" },
        { time: "18h40-19h15", title: "Lectio Divina" },
      ],
    },
    {
      day: "Mardi",
      subtitle: "",
      slots: [
        { time: "8h", title: "Laudes" },
        { time: "17h30-18h", title: "Adoration" },
        { time: "18h-18h40", title: "Vêpres + Eucharistie" },
      ],
    },
    {
      day: "Mercredi",
      subtitle: "",
      slots: [
        { time: "8h", title: "Laudes" },
        { time: "18h-18h40", title: "Vêpres + Eucharistie" },
      ],
    },
    {
      day: "Jeudi",
      subtitle: "",
      slots: [
        { time: "8h", title: "Laudes" },
        { time: "18h-18h40", title: "Vêpres + Eucharistie" },
        { time: "18h40-19h15", title: "Lectio Divina" },
        { time: "20h30", title: "Assemblée de prière" },
      ],
    },
    {
      day: "Vendredi",
      subtitle: "",
      slots: [
        { time: "8h", title: "Laudes" },
        { time: "17h30-18h", title: "Adoration" },
        { time: "18h-18h40", title: "Vêpres + Eucharistie" },
      ],
    },
    {
      day: "Samedi",
      subtitle: "",
      slots: [
        { time: "8h", title: "Laudes" },
        { time: "18h-18h40", title: "Vêpres" },
      ],
    },
    {
      day: "Dimanche",
      subtitle: "et fêtes",
      slots: [
        { time: "8h30", title: "Laudes" },
        { time: "11h-12h", title: "Eucharistie" },
        { time: "16h-16h15", title: "Bénédiction" },
        { time: "16h15-16h40", title: "Vêpres" },
      ],
    },
  ],
  news: [
    {
      image: "/assets/medias/img/gallery-home_basilique.jpg",
      alt: "",
      title: "La billeterie pour les concerts de printemps est ouverte!",
      text: FALLBACK_NEWS_TEXT,
      date: "18/03/2026",
    },
    {
      image: "/assets/medias/img/gallery-home_basilique.jpg",
      alt: "",
      title: "La billeterie pour les concerts de printemps est ouverte!",
      text: FALLBACK_NEWS_TEXT,
      date: "18/03/2026",
    },
    {
      image: "/assets/medias/img/gallery-home_basilique.jpg",
      alt: "",
      title: "La billeterie pour les concerts de printemps est ouverte!",
      text: FALLBACK_NEWS_TEXT,
      date: "18/03/2026",
    },
  ],
};

export default async function () {
  return fetchCraftEntry("community", FALLBACK_COMMUNITY, "community");
}
