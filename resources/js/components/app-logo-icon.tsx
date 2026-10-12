import type { SVGAttributes } from 'react';

/** A closed barrier gate: the post, its pivot, and a striped arm. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="9" width="5" height="13" rx="1" />
            <circle cx="4.5" cy="9" r="2.5" />
            <rect x="8" y="7.5" width="4" height="3" rx="0.5" />
            <rect x="13" y="7.5" width="4" height="3" rx="0.5" />
            <rect x="18" y="7.5" width="4" height="3" rx="0.5" />
        </svg>
    );
}
