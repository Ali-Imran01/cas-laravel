import { useId } from 'react';

/** Labelled <select>; `options` is [{value, label}]. */
export default function SelectField({ label, error, options, placeholder, ...select }) {
    const id = useId();

    return (
        <div className="text-sm">
            <label htmlFor={id}>{label}</label>
            <select
                id={id}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                className="mt-1 w-full rounded border bg-white px-3 py-2"
                {...select}
            >
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            {error && <p id={`${id}-error`} className="mt-1 text-red-700">{error}</p>}
        </div>
    );
}
