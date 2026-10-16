import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

export default function CbpModulesDropdown() {
    const { cbpModulesNav, staffWebBaseUrl } = usePage().props;
    const nav = cbpModulesNav || { home: {}, modules: [] };
    const home = nav.home || {};
    const modules = nav.modules || [];
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);

    const homeHref = home.href || `${staffWebBaseUrl || ''}/home/index`;
    const homeLabel = home.label || 'CBP Home';
    const homeActive = Boolean(home.is_active);
    let toggleActive = homeActive;
    for (const mod of modules) {
        if (mod.is_active) {
            toggleActive = true;
            break;
        }
    }

    useEffect(() => {
        function onDocClick(e) {
            if (rootRef.current && !rootRef.current.contains(e.target)) {
                setOpen(false);
            }
        }
        function onKey(e) {
            if (e.key === 'Escape') {
                setOpen(false);
            }
        }
        document.addEventListener('click', onDocClick);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keydown', onKey);
        };
    }, []);

    return (
        <li className={`nav-item cbp-modules-dd${open ? ' is-open' : ''}`} ref={rootRef} id="cbp-modules-dd">
            <button
                type="button"
                className={`cbp-modules-dd-toggle nav-link border-0${toggleActive ? ' is-active' : ''}`}
                id="cbp-modules-dd-btn"
                aria-haspopup="true"
                aria-expanded={open}
                aria-controls="cbp-modules-dd-panel"
                title="CBP Modules"
                onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setOpen((v) => !v);
                }}
            >
                <i className="bx bx-category" style={{ color: '#fff', fontSize: '1.1rem' }} aria-hidden="true" />
                <span className="cbp-modules-dd-label ms-2 d-none d-md-inline" style={{ color: '#fff', fontSize: '0.875rem' }}>
                    CBP Modules
                </span>
                <span className="cbp-modules-dd-caret d-none d-md-inline" aria-hidden="true">
                    ▼
                </span>
            </button>
            <div className="cbp-modules-dd-panel" id="cbp-modules-dd-panel" role="menu">
                <a
                    href={homeHref}
                    className={`cbp-modules-dd-primary${homeActive ? ' is-active' : ''}`}
                    role="menuitem"
                >
                    <span className="cbp-modules-dd-primary-title">{homeLabel}</span>
                </a>
                {modules.length > 0 ? (
                    <>
                        <p className="cbp-modules-dd-section">Systems</p>
                        {modules.map((mod) => (
                            <ModuleLink key={mod.id || mod.href} mod={mod} />
                        ))}
                    </>
                ) : (
                    <p className="cbp-modules-dd-empty" role="status">
                        No other CBP systems are assigned to your account.
                    </p>
                )}
            </div>
        </li>
    );
}

function ModuleLink({ mod }) {
    let icon = (mod.icon || 'fa-th').trim();
    if (icon && !icon.startsWith('fa ')) {
        if (icon.startsWith('fa-')) {
            icon = `fa ${icon}`;
        }
    }
    const href = mod.href || '#';
    const absolute = Boolean(mod.opens_in_new_tab);
    const active = Boolean(mod.is_active);

    return (
        <a
            href={href}
            className={`cbp-modules-dd-item${active ? ' is-active' : ''}`}
            role="menuitem"
            target={absolute ? '_blank' : undefined}
            rel={absolute ? 'noopener noreferrer' : undefined}
        >
            <i className={`${icon} cbp-modules-dd-icon`} aria-hidden="true" />
            <span className="cbp-modules-dd-item-text">
                <span className="cbp-modules-dd-item-label">{mod.label || 'Module'}</span>
            </span>
        </a>
    );
}
