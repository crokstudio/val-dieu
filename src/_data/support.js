import { fetchCraftEntry } from "../utils/craft-api.js";

const FALLBACK_PROJECT_TEXT =
  "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";

const FALLBACK_SUPPORT = {
  projects: [
    {
      image: "/assets/medias/img/gallery-home_basilique.jpg",
      alt: "",
      title: "Projet en cours",
      text: FALLBACK_PROJECT_TEXT,
    },
    {
      image: "/assets/medias/img/gallery-home_basilique.jpg",
      alt: "",
      title: "Projet en cours",
      text: FALLBACK_PROJECT_TEXT,
    },
    {
      image: "/assets/medias/img/gallery-home_basilique.jpg",
      alt: "",
      title: "Projet en cours",
      text: FALLBACK_PROJECT_TEXT,
    },
  ],
};

export default async function () {
  return fetchCraftEntry("support", FALLBACK_SUPPORT, "support");
}
