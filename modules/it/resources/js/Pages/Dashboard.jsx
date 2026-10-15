import { usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Dashboard({ pageTitle }) {
    const { auth } = usePage().props;
    return (
        <AppLayout title={pageTitle || 'Finance Dashboard'} user={auth?.user}>
            <h1 className="finance-page-title">Welcome to Finance Management</h1>
            <p className="text-muted mb-4">Africa CDC Central Business Platform</p>
            <div className="row g-4">
                <div className="col-12 col-md-6 col-lg-4">
                    <div className="border rounded p-4 h-100">
                        <i className="fas fa-chart-line fa-2x text-success mb-3" />
                        <h5>Financial Reports</h5>
                        <p className="text-muted small mb-0">View and generate financial reports (migration in progress).</p>
                    </div>
                </div>
                <div className="col-12 col-md-6 col-lg-4">
                    <div className="border rounded p-4 h-100">
                        <i className="fas fa-file-invoice-dollar fa-2x text-success mb-3" />
                        <h5>Invoices</h5>
                        <p className="text-muted small mb-0">Manage invoices and payment workflows.</p>
                    </div>
                </div>
                <div className="col-12 col-md-6 col-lg-4">
                    <div className="border rounded p-4 h-100">
                        <i className="fas fa-wallet fa-2x text-success mb-3" />
                        <h5>Budgets</h5>
                        <p className="text-muted small mb-0">Track division and programme budgets.</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
