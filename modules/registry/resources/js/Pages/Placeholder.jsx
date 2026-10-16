import { usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Placeholder({ pageTitle }) {
    const { auth } = usePage().props;
    return (
        <AppLayout title={pageTitle} user={auth?.user}>
            <p className="text-muted mb-0">This screen is being migrated from the legacy React app to Laravel Inertia.</p>
        </AppLayout>
    );
}
