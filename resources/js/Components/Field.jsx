import { useId } from 'react';

/** Labelled input with its validation error wired up for screen readers. */
export default function Field({ label, error, ...input }) {
    const id = useId();

    return (
        <div className="text-sm">
            <label htmlFor={id}>{label}</label>
            <input
                id={id}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                className="mt-1 w-full rounded border px-3 py-2"
                {...input}
            />
            {error && <p id={`${id}-error`} className="mt-1 text-red-700">{error}</p>}
        </div>
    );
}

export const PrimaryButton = ({ children, ...props }) => (
    <button className="w-full rounded bg-slate-900 py-2 text-white disabled:opacity-60" {...props}>{children}</button>
);
