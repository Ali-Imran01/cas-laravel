/** Success message (flash.status) and the action-level error some server actions raise (errors.user). */
export default function FlashMessages({ flash = {}, errors = {} }) {
    return (
        <>
            {flash.status && <p role="status" className="rounded bg-emerald-50 p-2 text-sm text-emerald-800">{flash.status}</p>}
            {errors.user && <p role="alert" className="rounded bg-red-50 p-2 text-sm text-red-800">{errors.user}</p>}
        </>
    );
}
