import { useId } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    /** Arm up when true. Follows the device's real portal_status. */
    open: boolean;
    /** Offline devices are drawn dimmed, with the lamp off. */
    online: boolean;
    /**
     * Changing this number replays the ripple at the card reader, e.g. once
     * per new scan. 0 draws no ripple.
     */
    scanKey?: number;
    className?: string;
};

// Arm pivot, in viewBox units.
const PIVOT_X = 104;
const PIVOT_Y = 128;
const ARM_LENGTH = 336;
const ARM_HEIGHT = 18;
// Raised almost upright, like the real servo at 90 degrees.
const OPEN_ANGLE = -84;
// The viewBox starts this far above the ground scene, so the raised arm
// (ARM_LENGTH tall at OPEN_ANGLE) stays inside the drawing.
const TOP = -216;

/**
 * A parking barrier gate drawn in SVG: cabinet with a card reader and a
 * status lamp, a striped arm that swings up and down, and the post the arm
 * rests on when closed. The arm takes about as long to move as the real
 * servo does.
 */
export function GateScene({ open, online, scanKey = 0, className }: Props) {
    // useId() contains colons; keep url(#...) references plain.
    const id = useId().replace(/:/g, '');
    const armClip = `${id}-arm`;
    const stripes = `${id}-stripes`;

    return (
        <svg
            viewBox={`0 ${TOP} 480 ${300 - TOP}`}
            role="img"
            aria-label={`Palang ${open ? 'terbuka' : 'tertutup'}${online ? '' : ', perangkat offline'}`}
            className={cn('h-auto w-full transition-[opacity,filter] duration-500', !online && 'opacity-55 grayscale', className)}
        >
            <defs>
                {/* Both in the arm's own coordinates: pivot at 0,0, arm along +x. */}
                <clipPath id={armClip}>
                    <rect x="0" y={-ARM_HEIGHT / 2} width={ARM_LENGTH} height={ARM_HEIGHT} rx={ARM_HEIGHT / 2} />
                </clipPath>
                <pattern id={stripes} width="48" height={ARM_HEIGHT} y={-ARM_HEIGHT / 2} patternUnits="userSpaceOnUse">
                    <rect width="24" height={ARM_HEIGHT} className="fill-primary" />
                </pattern>
            </defs>

            {/* Lane: ground line and dashed road marking. */}
            <line x1="0" y1="262" x2="480" y2="262" className="stroke-foreground/30" strokeWidth="2" />
            {Array.from({ length: 6 }).map((_, i) => (
                <rect key={i} x={150 + i * 56} y="276" width="32" height="5" className="fill-foreground/15" />
            ))}

            {/* Rest post the closed arm sits on. */}
            <rect x="432" y="138" width="14" height="124" rx="2" className="fill-card stroke-foreground" strokeWidth="2" />
            <path d="M424 138h30" className="stroke-foreground" strokeWidth="3" strokeLinecap="round" />

            {/* Cabinet. */}
            <rect x="44" y="108" width="96" height="154" rx="6" className="fill-card stroke-foreground" strokeWidth="2" />
            <rect x="44" y="108" width="96" height="22" rx="6" className="fill-foreground" />

            {/* Card reader on the cabinet's face, with the scan ripple. */}
            <rect x="62" y="168" width="60" height="44" rx="4" className="fill-background stroke-foreground" strokeWidth="2" />
            <path
                d="M84 182a10 10 0 0 1 0 16M90 178a16 16 0 0 1 0 24"
                className="stroke-foreground fill-none"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <circle cx="78" cy="190" r="3" className="fill-foreground" />
            {scanKey > 0 && (
                <circle
                    key={scanKey}
                    cx="92"
                    cy="190"
                    r="18"
                    className="stroke-primary motion-safe:animate-scan-ping origin-center fill-none opacity-0 [transform-box:fill-box]"
                    strokeWidth="3"
                />
            )}
            <rect x="62" y="226" width="60" height="6" rx="3" className="fill-foreground/20" />

            {/* Status lamp: lit while the gate is open. */}
            {open && online && <circle cx="70" cy="96" r="16" className="fill-primary/30 motion-safe:animate-pulse" />}
            <circle
                cx="70"
                cy="96"
                r="8"
                className={cn(
                    'stroke-foreground transition-colors duration-500',
                    open && online ? 'fill-primary' : 'fill-muted',
                )}
                strokeWidth="2"
            />

            {/* Arm: moved so the pivot is the origin, then rotated about it. */}
            <g transform={`translate(${PIVOT_X} ${PIVOT_Y})`}>
                <g
                    className="transition-transform duration-[900ms] ease-in-out motion-reduce:transition-none"
                    style={{ transform: `rotate(${open ? OPEN_ANGLE : 0}deg)` }}
                >
                    <g clipPath={`url(#${armClip})`}>
                        <rect x="0" y={-ARM_HEIGHT / 2} width={ARM_LENGTH} height={ARM_HEIGHT} className="fill-card" />
                        <rect
                            x="24"
                            y={-ARM_HEIGHT / 2}
                            width={ARM_LENGTH - 24}
                            height={ARM_HEIGHT}
                            fill={`url(#${stripes})`}
                        />
                    </g>
                    <rect
                        x="0"
                        y={-ARM_HEIGHT / 2}
                        width={ARM_LENGTH}
                        height={ARM_HEIGHT}
                        rx={ARM_HEIGHT / 2}
                        className="stroke-foreground fill-none"
                        strokeWidth="2"
                    />
                </g>
            </g>

            {/* Pivot hub on top of the arm. */}
            <circle cx={PIVOT_X} cy={PIVOT_Y} r="14" className="fill-foreground" />
            <circle cx={PIVOT_X} cy={PIVOT_Y} r="5" className="fill-card" />
        </svg>
    );
}
