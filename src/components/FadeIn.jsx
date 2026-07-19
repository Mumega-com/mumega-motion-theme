import { m } from 'motion/react';

export const FADE_IN_DEFAULTS = {
	delay: 0,
	y: 24,
	duration: 0.5,
};

/**
 * Fades and slides children into view once on mount.
 *
 * Content is real, server-rendered HTML underneath — this only adds the
 * entrance animation on top, so there's no flash-of-empty-content and no-JS
 * visitors still get the full content immediately.
 */
export default function FadeIn( {
	children,
	delay = FADE_IN_DEFAULTS.delay,
	y = FADE_IN_DEFAULTS.y,
	duration = FADE_IN_DEFAULTS.duration,
	as = 'div',
	...rest
} ) {
	const Tag = m[ as ] || m.div;
	return (
		<Tag
			initial={ { opacity: 0, y } }
			animate={ { opacity: 1, y: 0 } }
			transition={ { duration, delay, ease: 'easeOut' } }
			{ ...rest }
		>
			{ children }
		</Tag>
	);
}
