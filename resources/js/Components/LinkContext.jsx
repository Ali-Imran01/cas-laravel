import { createContext, useContext } from 'react';

// Pages stay router-agnostic: each entry (Inertia app / static demo) supplies its own Link.
const LinkContext = createContext(({ href, ...props }) => <a href={href} {...props} />);

export const LinkProvider = LinkContext.Provider;
export const useLink = () => useContext(LinkContext);
