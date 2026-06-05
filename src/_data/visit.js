import "dotenv/config";

export default async function () {
  const response = await fetch("http://localhost:8080/api/visit.json");
  return response.json();
}