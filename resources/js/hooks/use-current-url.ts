import { useLocation } from 'react-router-dom';

export type IsCurrentUrlFn = (
    urlToCheck: string,
    currentUrl?: string,
    startsWith?: boolean,
) => boolean;

export type IsCurrentOrParentUrlFn = (
    urlToCheck: string,
    currentUrl?: string,
) => boolean;

export type WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
    urlToCheck: string,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
) => TIfTrue | TIfFalse;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    isCurrentOrParentUrl: IsCurrentOrParentUrlFn;
    whenCurrentUrl: WhenCurrentUrlFn;
};

export function useCurrentUrl(): UseCurrentUrlReturn {
    const location = useLocation();
    const currentUrlPath = location.pathname;

    const isCurrentUrl: IsCurrentUrlFn = (
        urlToCheck: string,
        currentUrl?: string,
        startsWith: boolean = false,
    ) => {
        const urlToCompare = currentUrl ?? currentUrlPath;

        const comparePath = (path: string): boolean =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlToCheck.startsWith('http')) {
            return comparePath(urlToCheck);
        }

        try {
            const absoluteUrl = new URL(urlToCheck);

            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    };

    const isCurrentOrParentUrl: IsCurrentOrParentUrlFn = (
        urlToCheck: string,
        currentUrl?: string,
    ) => {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    };

    const whenCurrentUrl: WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
        urlToCheck: string,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
