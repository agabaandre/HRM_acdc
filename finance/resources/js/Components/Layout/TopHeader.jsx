import { router, usePage } from '@inertiajs/react';
import CbpModulesDropdown from './CbpModulesDropdown';

export default function TopHeader({ user }) {
    const { staffWebBaseUrl, routes } = usePage().props;
    const staffBase = staffWebBaseUrl || 'http://localhost/staff';
    const profileUrl = `${staffBase}/auth/profile`;
    const passwordUrl = `${staffBase}/auth/users`;
    const assetsBase = `${staffBase}/apm`;

    const avatar = buildAvatar(user, staffBase);

    return (
        <header>
            <div className="topbar d-flex">
                <nav className="navbar navbar-expand">
                    <div className="topbar-logo-header">
                        <img
                            src={`${assetsBase}/assets/images/AU_CDC_Logo-800.png`}
                            width="200"
                            alt="Africa CDC"
                            style={{ filter: 'brightness(0) invert(1)' }}
                        />
                    </div>
                    <div className="mobile-toggle-menu d-xl-none">
                        <i className="bx bx-menu" aria-hidden="true" />
                    </div>
                    <div className="top-menu ms-auto">
                        <ul className="navbar-nav align-items-center">
                            <CbpModulesDropdown />
                        </ul>
                    </div>
                    <div className="user-box dropdown">
                        <a
                            className="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            {avatar}
                            <div className="user-info ps-3">
                                <p className="user-name mb-0">{user?.name || user?.fname || 'User'}</p>
                            </div>
                        </a>
                        <ul className="dropdown-menu dropdown-menu-end">
                            <li>
                                <a className="dropdown-item" href={profileUrl}>
                                    <i className="fas fa-user" /> <span>Profile</span>
                                </a>
                            </li>
                            <li>
                                <a className="dropdown-item" href={passwordUrl}>
                                    <i className="fas fa-key" /> <span>Change Password</span>
                                </a>
                            </li>
                            <li>
                                <div className="dropdown-divider mb-0" />
                            </li>
                            <li>
                                <a
                                    className="dropdown-item"
                                    href={routes?.logout || '/logout'}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        router.visit(routes?.logout || '/logout');
                                    }}
                                >
                                    <i className="bx bx-log-out-circle" /> <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </header>
    );
}

function buildAvatar(user, staffBase) {
    if (user?.photo) {
        const src = user.photo_data
            ? `data:image/jpeg;base64,${user.photo_data}`
            : `${staffBase}/uploads/staff/${user.photo}`;
        return <img src={src} className="user-img" alt="" width={40} height={40} style={{ borderRadius: '50%' }} />;
    }
    const first = (user?.fname || user?.name || 'U').charAt(0).toUpperCase();
    const last = user?.lname ? user.lname.charAt(0).toUpperCase() : '';
    const colors = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240'];
    const bg = colors[(first.charCodeAt(0) - 65) % colors.length] || colors[0];
    return (
        <div
            className="user-avatar text-white d-flex align-items-center justify-content-center"
            style={{
                width: 40,
                height: 40,
                borderRadius: '50%',
                backgroundColor: bg,
                fontWeight: 600,
            }}
        >
            {first}
            {last}
        </div>
    );
}
