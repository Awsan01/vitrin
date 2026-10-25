import type { SVGAttributes } from 'react';

/**
 * The Vitrin mark: an arched shop window with a product on its shelf.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M5 29V14C5 7.925 9.925 3 16 3s11 4.925 11 11v15H5Zm4-4V14a7 7 0 0 1 14 0v11H9Zm3.5 0v-4.5h7V25h-7Z"
            />
        </svg>
    );
}
