import { createContext, useContext } from 'react';

// Which permission names the signed-in user holds. The Inertia entry supplies the real list; the static
// demo keeps the default, which allows everything. The server still enforces every check.
const AccessContext = createContext(() => true);

export const AccessProvider = AccessContext.Provider;
export const useCan = () => useContext(AccessContext);
