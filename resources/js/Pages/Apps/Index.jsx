import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import Field from '../../Components/Field';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import SelectField from '../../Components/SelectField';

const button = 'rounded border px-3 py-1 text-sm';
const primary = 'rounded bg-slate-900 px-3 py-1.5 text-sm text-white';
const ENVIRONMENTS = ['production', 'staging', 'sandbox'];
const STATUS_TONE = { active: 'bg-emerald-100 text-emerald-800', sandbox: 'bg-sky-100 text-sky-800', disabled: 'bg-slate-200 text-slate-700' };

const Scopes = ({ scopes, value, onChange, error }) => {
    const { t } = useTranslation();

    return (
        <fieldset className="space-y-1 text-sm">
            <legend>{t('appPage.scopes')}</legend>
            {scopes.map((s) => (
                <label key={s.name} className="flex items-start gap-2">
                    <input
                        type="checkbox"
                        className="mt-1"
                        checked={value.includes(s.name)}
                        onChange={(e) => onChange(e.target.checked ? [...value, s.name] : value.filter((x) => x !== s.name))}
                    />
                    <span><code>{s.name}</code> <span className="text-slate-500">{s.description}</span></span>
                </label>
            ))}
            {error && <p role="alert" className="text-red-700">{error}</p>}
        </fieldset>
    );
};

function AppForm({ app, scopes, defaultScopes, errors, onSubmit }) {
    const { t } = useTranslation();
    const [v, setV] = useState({
        code: '', name: app?.name ?? '', environment: app?.environment ?? 'production', homepage_url: app?.homepage_url ?? '',
        color: app?.color ?? '', redirect_uris: (app?.redirect_uris ?? []).join('\n'), allowed_scopes: app?.allowed_scopes ?? defaultScopes,
    });
    const set = (key) => (e) => setV((s) => ({ ...s, [key]: e.target.value }));

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit?.({ ...v, homepage_url: v.homepage_url || null, color: v.color || null }); }} className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label={t('appPage.name')} value={v.name} onChange={set('name')} error={errors.name} />
                {!app && <Field label={t('appPage.code')} value={v.code} onChange={set('code')} error={errors.code} placeholder="asset-inspection" />}
                <SelectField label={t('appPage.environment')} value={v.environment} onChange={set('environment')} error={errors.environment} options={ENVIRONMENTS.map((x) => ({ value: x, label: t(`appPage.env.${x}`) }))} />
                <Field label={t('appPage.homepage')} type="url" value={v.homepage_url} onChange={set('homepage_url')} error={errors.homepage_url} placeholder="https://" />
                <Field label={t('appPage.color')} value={v.color} onChange={set('color')} error={errors.color} placeholder="#2563eb" maxLength={7} />
            </div>
            <div className="text-sm">
                <label htmlFor="redirect-uris">{t('appPage.redirects')}</label>
                <textarea
                    id="redirect-uris"
                    rows={3}
                    value={v.redirect_uris}
                    onChange={set('redirect_uris')}
                    aria-invalid={errors.redirect_uris || errors['redirect_uris.0'] ? 'true' : undefined}
                    className="mt-1 w-full rounded border px-3 py-2 font-mono text-xs"
                />
                <p className="text-xs text-slate-500">{t('appPage.redirectsHint')}</p>
                {(errors.redirect_uris || errors['redirect_uris.0']) && <p role="alert" className="text-red-700">{errors.redirect_uris ?? errors['redirect_uris.0']}</p>}
            </div>
            <Scopes scopes={scopes} value={v.allowed_scopes} onChange={(s) => setV((x) => ({ ...x, allowed_scopes: s }))} error={errors.allowed_scopes} />
            <button className={primary}>{app ? t('appPage.save') : t('appPage.register')}</button>
        </form>
    );
}

function Webhook({ app, errors, onUpdate, onRotate }) {
    const { t } = useTranslation();
    const [url, setUrl] = useState(app.webhook_url ?? '');

    return (
        <section className="space-y-3">
            <h3 className="font-medium">{t('appPage.webhook')}</h3>
            <p className="text-sm text-slate-500">{t('appPage.webhookHint')}</p>
            <form onSubmit={(e) => { e.preventDefault(); onUpdate?.(url.trim() || null); }} className="flex flex-wrap items-end gap-2">
                <Field label={t('appPage.webhookUrl')} value={url} onChange={(e) => setUrl(e.target.value)} error={errors.webhook_url} placeholder="https://" />
                <button className={primary}>{t('appPage.save')}</button>
            </form>
            {app.webhook_url && (
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-slate-500">{app.webhook_configured ? t('appPage.webhookSigned') : t('appPage.webhookUnsigned')}</span>
                    <button type="button" className={button} onClick={onRotate}>{t('appPage.rotateWebhookSecret')}</button>
                </div>
            )}
        </section>
    );
}

function Access({ app, roles, errors, onMapRole, onUnmapRole, onGrantUser, onRevokeUser }) {
    const { t } = useTranslation();
    const [role, setRole] = useState({ role_id: '', app_role: '' });
    const [grant, setGrant] = useState({ staff_id: '', app_role: '', expires_at: '' });

    return (
        <section className="space-y-4">
            <h3 className="font-medium">{t('appPage.access')}</h3>
            <p className="text-sm text-slate-500">{t('appPage.accessHint')}</p>

            <ul className="divide-y rounded border text-sm">
                {app.roles.length === 0 && <li className="p-2 text-slate-500">{t('appPage.noRoles')}</li>}
                {app.roles.map((r) => (
                    <li key={r.id} className="flex items-center justify-between p-2">
                        <span><code>{r.name}</code> → {r.app_role}</span>
                        <button type="button" className={button} onClick={() => onUnmapRole?.(r.id)}>{t('appPage.remove')}</button>
                    </li>
                ))}
            </ul>
            <form onSubmit={(e) => { e.preventDefault(); onMapRole?.(role); setRole({ role_id: '', app_role: '' }); }} className="flex flex-wrap items-end gap-2">
                <SelectField label={t('appPage.casRole')} value={role.role_id} onChange={(e) => setRole({ ...role, role_id: e.target.value })} error={errors.role_id} placeholder="" options={roles.map((r) => ({ value: r.id, label: r.display_name }))} />
                <Field label={t('appPage.appRole')} value={role.app_role} onChange={(e) => setRole({ ...role, app_role: e.target.value })} error={errors.app_role} />
                <button className={primary} disabled={!role.role_id}>{t('appPage.mapRole')}</button>
            </form>

            <ul className="divide-y rounded border text-sm">
                {app.grants.length === 0 && <li className="p-2 text-slate-500">{t('appPage.noGrants')}</li>}
                {app.grants.map((g) => (
                    <li key={g.id} className="flex flex-wrap items-center justify-between gap-2 p-2">
                        <span>{g.name} <span className="text-slate-500">({g.staff_id})</span> → {g.app_role}{g.expires_at && <span className="text-slate-500"> · {t('appPage.until', { date: g.expires_at.slice(0, 10) })}</span>}</span>
                        <button type="button" className={button} onClick={() => onRevokeUser?.(g.id)}>{t('appPage.remove')}</button>
                    </li>
                ))}
            </ul>
            <form onSubmit={(e) => { e.preventDefault(); onGrantUser?.({ ...grant, expires_at: grant.expires_at || null }); setGrant({ staff_id: '', app_role: '', expires_at: '' }); }} className="grid gap-3 sm:grid-cols-4">
                <Field label={t('appPage.staffId')} value={grant.staff_id} onChange={(e) => setGrant({ ...grant, staff_id: e.target.value })} error={errors.staff_id} />
                <Field label={t('appPage.appRole')} value={grant.app_role} onChange={(e) => setGrant({ ...grant, app_role: e.target.value })} error={errors.app_role} />
                <Field label={t('appPage.expires')} type="date" value={grant.expires_at} onChange={(e) => setGrant({ ...grant, expires_at: e.target.value })} error={errors.expires_at} />
                <div className="flex items-end"><button className={primary}>{t('appPage.grant')}</button></div>
            </form>
        </section>
    );
}

// Dumb page: every action is a callback from the entry point; choosing an app is a plain link (?app=ID).
export default function Index({
    apps = [], selected, scopes = [], defaultScopes = [], roles = [], endpoints = {}, can = {}, flash = {}, errors = {},
    onRegisterApp, onUpdateApp, onRotateSecret, onDisableApp, onEnableApp, onDeleteApp, onMapRole, onUnmapRole, onGrantUser, onRevokeUser,
    onUpdateWebhook, onRotateWebhookSecret, onSignOut,
}) {
    const { t, i18n } = useTranslation();
    const Link = useLink();
    const [confirm, setConfirm] = useState(null); // 'rotate' | 'disable' | 'delete'
    const disabled = selected?.status === 'disabled';

    const run = (fn) => { fn?.(); setConfirm(null); };

    return (
        <AppLayout current="apps" onSignOut={onSignOut}>
            <div className="space-y-4">
                <h1 className="text-xl font-semibold">{t('nav.apps')}</h1>
                <FlashMessages flash={flash} errors={errors} />
                {flash.appSecret && (
                    <div role="alert" className="rounded border-2 border-amber-400 bg-amber-50 p-3 text-sm">
                        <div className="font-medium">{t('appPage.secretOnce')}</div>
                        <code className="mt-1 block break-all rounded bg-white p-2 select-all">{flash.appSecret}</code>
                    </div>
                )}
                {flash.webhookSecret && (
                    <div role="alert" className="rounded border-2 border-amber-400 bg-amber-50 p-3 text-sm">
                        <div className="font-medium">{t('appPage.webhookSecretOnce')}</div>
                        <code className="mt-1 block break-all rounded bg-white p-2 select-all">{flash.webhookSecret}</code>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,18rem)_1fr]">
                    <div className="space-y-6">
                        <nav aria-label={t('nav.apps')} className="rounded-lg bg-white p-2 shadow-sm">
                            {apps.length === 0 && <p className="p-2 text-sm text-slate-500">{t('appPage.empty')}</p>}
                            <ul>
                                {apps.map((a) => (
                                    <li key={a.id}>
                                        <Link
                                            href={`/apps?app=${a.id}`}
                                            aria-current={selected?.id === a.id ? 'true' : undefined}
                                            className={`flex items-center justify-between rounded px-2 py-1.5 text-sm ${selected?.id === a.id ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'}`}
                                        >
                                            <span>{a.name}</span>
                                            <span className={`rounded px-1.5 text-xs ${selected?.id === a.id ? 'bg-white text-slate-900' : STATUS_TONE[a.status]}`}>{t(`appPage.status.${a.status}`)}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                        {can.create && (
                            <details className="rounded-lg bg-white p-4 shadow-sm" open={apps.length === 0}>
                                <summary className="cursor-pointer font-medium">{t('appPage.newApp')}</summary>
                                <div className="mt-3"><AppForm scopes={scopes} defaultScopes={defaultScopes} errors={errors} onSubmit={onRegisterApp} /></div>
                            </details>
                        )}
                    </div>

                    {selected && (
                        <div className="space-y-6 rounded-lg bg-white p-4 shadow-sm">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-lg font-semibold">{selected.name}</h2>
                                    <span className={`rounded px-2 py-0.5 text-xs ${STATUS_TONE[selected.status]}`}>{t(`appPage.status.${selected.status}`)}</span>
                                </div>
                                <p className="text-sm text-slate-500">
                                    <code>{selected.code}</code> · {t(`appPage.env.${selected.environment}`)}{selected.owner && ` · ${t('appPage.owner', { name: selected.owner })}`}
                                </p>
                            </div>

                            <dl className="grid gap-2 rounded bg-slate-50 p-3 text-sm">
                                <div><dt className="text-slate-500">{t('appPage.clientId')}</dt><dd><code className="break-all select-all">{selected.client_id}</code></dd></div>
                                <div><dt className="text-slate-500">{t('appPage.discoveryUrl')}</dt><dd><code className="break-all select-all">{endpoints.discovery}</code></dd></div>
                                <div><dt className="text-slate-500">{t('appPage.authorizeUrl')}</dt><dd><code className="break-all select-all">{endpoints.authorization}</code></dd></div>
                                <div><dt className="text-slate-500">{t('appPage.tokenUrl')}</dt><dd><code className="break-all select-all">{endpoints.token}</code></dd></div>
                                <div><dt className="text-slate-500">{t('appPage.userinfoUrl')}</dt><dd><code className="break-all select-all">{endpoints.userinfo}</code></dd></div>
                                <div><dt className="text-slate-500">{t('appPage.secret')}</dt><dd>{selected.secret_rotated_at ? t('appPage.rotatedOn', { date: new Date(selected.secret_rotated_at).toLocaleDateString(i18n.language) }) : t('appPage.secretHidden')}</dd></div>
                            </dl>

                            {can.update && <AppForm key={`f${selected.id}:${selected.redirect_uris.join()}:${selected.allowed_scopes.join()}`} app={selected} scopes={scopes} defaultScopes={defaultScopes} errors={errors} onSubmit={onUpdateApp} />}

                            {can.update && <Access key={`a${selected.id}`} app={selected} roles={roles} errors={errors} onMapRole={onMapRole} onUnmapRole={onUnmapRole} onGrantUser={onGrantUser} onRevokeUser={onRevokeUser} />}

                            {can.update && (
                                <Webhook
                                    key={`w${selected.id}:${selected.webhook_url}`}
                                    app={selected}
                                    errors={errors}
                                    onUpdate={onUpdateWebhook}
                                    onRotate={onRotateWebhookSecret}
                                />
                            )}

                            {can.update && (
                                <section className="space-y-2">
                                    <h3 className="font-medium">{t('appPage.danger')}</h3>
                                    {confirm ? (
                                        <div role="alertdialog" aria-label={t(`appPage.${confirm}`)} className="flex flex-wrap items-center gap-2 rounded bg-red-50 p-3 text-sm">
                                            <span>{t(`appPage.confirm.${confirm}`, { name: selected.name })}</span>
                                            <button type="button" className="rounded bg-red-700 px-3 py-1 text-white" onClick={() => run({ rotate: onRotateSecret, disable: onDisableApp, delete: onDeleteApp }[confirm])}>{t('appPage.yes')}</button>
                                            <button type="button" className={button} onClick={() => setConfirm(null)}>{t('appPage.cancel')}</button>
                                        </div>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            <button type="button" className={button} onClick={() => setConfirm('rotate')}>{t('appPage.rotate')}</button>
                                            {disabled
                                                ? <button type="button" className={button} onClick={onEnableApp}>{t('appPage.enable')}</button>
                                                : <button type="button" className={button} onClick={() => setConfirm('disable')}>{t('appPage.disable')}</button>}
                                            {can.delete && disabled && <button type="button" className={`${button} text-red-700`} onClick={() => setConfirm('delete')}>{t('appPage.delete')}</button>}
                                        </div>
                                    )}
                                </section>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
