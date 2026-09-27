import AppLayout from '../../Components/AppLayout';
import UserForm from '../../Components/UserForm';

// Dumb page: onSubmit(values) comes from the entry point.
export default function Create({ orgUnits, positions, roles, errors = {}, onSubmit, onSignOut }) {
    return (
        <AppLayout current="users" onSignOut={onSignOut}>
            <UserForm orgUnits={orgUnits} positions={positions} roles={roles} errors={errors} onSubmit={onSubmit} />
        </AppLayout>
    );
}
