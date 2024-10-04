// using react as external with esbuild
// we use this require function to map react with wordpress react wp.element or we are gonna have multiple react if we use other packages :S
window.require = (name) => {
  if (name === "react") return wp.element;
  throw new Error("Can't not require " + name);
}