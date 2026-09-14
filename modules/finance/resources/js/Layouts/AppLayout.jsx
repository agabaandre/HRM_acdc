import { Head } from '@inertiajs/react';
import TopHeader from '../Components/Layout/TopHeader';
import PrimaryNav from '../Components/Layout/PrimaryNav';
import Footer from '../Components/Layout/Footer';

export default function AppLayout({ children, title = 'Finance Management', user }) {
    return (
        <>
            <Head title={title} />
            <div className="wrapper">
                <TopHeader user={user} />
                <PrimaryNav />
                <div className="page-wrapper">
                    <div className="page-content">
                        <div className="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                            <div className="breadcrumb-title pe-3">{title}</div>
                        </div>
                        <div className="card">
                            <div className="card-body">{children}</div>
                        </div>
                    </div>
                </div>
                <Footer />
            </div>
        </>
    );
}
