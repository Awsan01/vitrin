import { useId } from 'react';
import type { SVGAttributes } from 'react';

/**
 * A Turkish tea glass on its saucer, drawn as a studio product shot. Used on
 * the landing page as the sample "product photo".
 */
export function TeaGlass(props: SVGAttributes<SVGSVGElement>) {
    const id = useId().replace(/:/g, '');
    const glass =
        'M82 50C82 95 104 118 104 150C104 172 86 186 86 214C86 236 100 248 120 248C140 248 154 236 154 214C154 186 136 172 136 150C136 118 158 95 158 50Z';

    return (
        <svg
            viewBox="0 0 240 290"
            role="img"
            aria-label="Turkish tea glass on a saucer"
            {...props}
        >
            <defs>
                <linearGradient id={`${id}-tea`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#C2521F" />
                    <stop offset="0.55" stopColor="#9A3412" />
                    <stop offset="1" stopColor="#5F1C08" />
                </linearGradient>
                <linearGradient id={`${id}-saucer`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#FFFFFF" />
                    <stop offset="1" stopColor="#DCE1DE" />
                </linearGradient>
                <radialGradient id={`${id}-shadow`}>
                    <stop offset="0" stopColor="#0E2A24" stopOpacity="0.28" />
                    <stop offset="1" stopColor="#0E2A24" stopOpacity="0" />
                </radialGradient>
                <clipPath id={`${id}-level`}>
                    <rect x="0" y="80" width="240" height="210" />
                </clipPath>
            </defs>

            <ellipse
                cx="120"
                cy="268"
                rx="100"
                ry="14"
                fill={`url(#${id}-shadow)`}
            />

            <ellipse
                cx="120"
                cy="254"
                rx="84"
                ry="17"
                fill={`url(#${id}-saucer)`}
            />
            <ellipse
                cx="120"
                cy="252"
                rx="79"
                ry="14.5"
                fill="none"
                stroke="#C9A24A"
                strokeWidth="1.5"
            />
            <ellipse cx="120" cy="250" rx="40" ry="7" fill="#E4E8E6" />

            <path
                d={glass}
                fill="#FFFFFF"
                fillOpacity="0.35"
                stroke="#0E2A24"
                strokeOpacity="0.12"
                strokeWidth="1.2"
            />
            <path
                d={glass}
                fill={`url(#${id}-tea)`}
                clipPath={`url(#${id}-level)`}
            />
            <ellipse cx="120" cy="80" rx="33.5" ry="3.6" fill="#D9773A" />

            <ellipse
                cx="120"
                cy="50"
                rx="38"
                ry="4.5"
                fill="#FFFFFF"
                fillOpacity="0.5"
            />
            <ellipse
                cx="120"
                cy="50"
                rx="38"
                ry="4.5"
                fill="none"
                stroke="#C9A24A"
                strokeWidth="2"
            />

            <path
                d="M90 60C90 95 109 118 109 150"
                fill="none"
                stroke="#FFFFFF"
                strokeOpacity="0.6"
                strokeWidth="3.2"
                strokeLinecap="round"
            />
            <path
                d="M94 200C93 220 99 232 108 238"
                fill="none"
                stroke="#FFFFFF"
                strokeOpacity="0.45"
                strokeWidth="2.6"
                strokeLinecap="round"
            />
            <path
                d="M147 70C147 92 138 108 133 122"
                fill="none"
                stroke="#FFFFFF"
                strokeOpacity="0.25"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </svg>
    );
}

/**
 * The same tea glass photographed from directly above.
 */
export function TeaGlassTop(props: SVGAttributes<SVGSVGElement>) {
    const id = useId().replace(/:/g, '');

    return (
        <svg
            viewBox="0 0 240 290"
            role="img"
            aria-label="Tea glass seen from above"
            {...props}
        >
            <defs>
                <radialGradient id={`${id}-saucer`} cx="0.42" cy="0.38">
                    <stop offset="0" stopColor="#FFFFFF" />
                    <stop offset="1" stopColor="#D9DEDB" />
                </radialGradient>
                <radialGradient id={`${id}-tea`} cx="0.4" cy="0.38">
                    <stop offset="0" stopColor="#D06A2E" />
                    <stop offset="0.6" stopColor="#9A3412" />
                    <stop offset="1" stopColor="#6B200B" />
                </radialGradient>
                <radialGradient id={`${id}-shadow`}>
                    <stop offset="0.7" stopColor="#0E2A24" stopOpacity="0.22" />
                    <stop offset="1" stopColor="#0E2A24" stopOpacity="0" />
                </radialGradient>
            </defs>

            <circle cx="126" cy="152" r="104" fill={`url(#${id}-shadow)`} />
            <circle cx="120" cy="145" r="92" fill={`url(#${id}-saucer)`} />
            <circle
                cx="120"
                cy="145"
                r="86"
                fill="none"
                stroke="#C9A24A"
                strokeWidth="1.5"
            />
            <circle
                cx="120"
                cy="145"
                r="50"
                fill="#FFFFFF"
                fillOpacity="0.55"
                stroke="#0E2A24"
                strokeOpacity="0.1"
            />
            <circle cx="120" cy="145" r="43" fill={`url(#${id}-tea)`} />
            <circle
                cx="120"
                cy="145"
                r="50"
                fill="none"
                stroke="#C9A24A"
                strokeWidth="2.5"
            />
            <path
                d="M93 128a33 33 0 0 1 22-15"
                fill="none"
                stroke="#FFFFFF"
                strokeOpacity="0.55"
                strokeWidth="3"
                strokeLinecap="round"
            />
        </svg>
    );
}
