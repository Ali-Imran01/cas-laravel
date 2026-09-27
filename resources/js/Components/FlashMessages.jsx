// Errors a server action raises about the record as a whole (not a single field).
const ACTION_ERRORS = ['user', 'unit', 'position', 'role', 'app', 'workflow', 'request'];

/** Success message (flash.status) and action-level errors. */
export default function FlashMessages({ flash = {}, errors = {} }) {
    const actionError = ACTION_ERRORS.map((k) => errors[k]).find(Boolean);

    return (
        <>
            {flash.status && <p role="status" className="rounded bg-emerald-50 p-2 text-sm text-emerald-800">{flash.status}</p>}
            {actionError && <p role="alert" className="rounded bg-red-50 p-2 text-sm text-red-800">{actionError}</p>}
        </>
    );
}
