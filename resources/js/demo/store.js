import { useCallback, useEffect, useState } from 'react';
import seed from './data/seed.json';

const PREFIX = 'cas:';
const CHANGED = 'cas:demo-changed';

const read = (key) => {
    try {
        const raw = localStorage.getItem(PREFIX + key);
        if (raw) return JSON.parse(raw);
    } catch {
        // storage blocked or corrupt: fall back to seed data
    }
    return seed[key] ?? [];
};

const write = (key, items) => {
    try {
        localStorage.setItem(PREFIX + key, JSON.stringify(items));
    } catch {
        // storage unavailable: changes last only until the next read
    }
    window.dispatchEvent(new Event(CHANGED));
};

// Fake CRUD over seed data, persisted per key in localStorage.
export function useDemo(key) {
    const [items, setItems] = useState(() => read(key));

    useEffect(() => {
        const sync = () => setItems(read(key));
        window.addEventListener(CHANGED, sync);
        return () => window.removeEventListener(CHANGED, sync);
    }, [key]);

    const create = useCallback((item) => write(key, [...read(key), { id: Date.now(), ...item }]), [key]);
    const update = useCallback((id, patch) => write(key, read(key).map((i) => (i.id === id ? { ...i, ...patch } : i))), [key]);
    const remove = useCallback((id) => write(key, read(key).filter((i) => i.id !== id)), [key]);

    return { items, create, update, remove };
}

export function resetDemo() {
    try {
        Object.keys(localStorage)
            .filter((k) => k.startsWith(PREFIX))
            .forEach((k) => localStorage.removeItem(k));
    } catch {
        // nothing to clear
    }
    window.dispatchEvent(new Event(CHANGED));
}
