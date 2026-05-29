import { Link, usePage } from '@inertiajs/react';

const MENU_ITEMS = [
    { key: 'dashboard', icon: 'fas fa-tachometer-alt', title: 'Dashboard' },
    { key: 'my-advances', icon: 'fas fa-hand-holding-usd', title: 'My Advances' },
    { key: 'my-missions', icon: 'fas fa-plane', title: 'My Missions' },
    { key: 'budgets', icon: 'fas fa-wallet', title: 'Budgets' },
];

const ROUTE_PATHS = {
    dashboard: '/dashboard',
    'my-advances': '/my-advances',
    'my-missions': '/my-missions',
    budgets: '/budgets',
};

export default function PrimaryNav() {
    const { url, routes, appUrl } = usePage().props;
    const base = (appUrl || '').replace(/\/$/, '');

    return (
        <div className="nav-container primary-menu">
            <nav className="navbar navbar-expand-xl w-100">
                <ul className="navbar-nav justify-content-start">
                    {MENU_ITEMS.map((item) => {
                        const href =
                            routes?.[item.key] ||
                            `${base}${ROUTE_PATHS[item.key] || `/${item.key}`}`;
                        const active = url === href || url.startsWith(`${href}?`);
                        return (
                            <li key={item.key} className="nav-item">
                                <Link href={href} className={`nav-link${active ? ' active' : ''}`} preserveScroll>
                                    <div className="parent-icon">
                                        <i className={item.icon} />
                                    </div>
                                    <div className="menu-title">{item.title}</div>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </div>
    );
}
