import { useEffect, useRef, useState } from 'react';
import { m } from 'motion/react';

/**
 * Renders text arriving token-by-token — either from a real streaming HTTP
 * endpoint (`streamUrl`, read via fetch + ReadableStream, the same mechanism
 * an actual LLM response stream uses) or a simulated typewriter over a
 * static `text` string when no endpoint is given.
 *
 * The `layout` prop on the wrapping motion.div is the actual point: as new
 * tokens arrive and the box grows taller, Motion runs a FLIP-based layout
 * animation — the box (and anything below it in normal flow) smoothly
 * spring-animates into its new size/position instead of snapping. That's
 * genuinely not available in Elementor: content-driven, physics-interpolated
 * reflow of the element itself and its siblings, not a one-off effect on a
 * single node.
 */
export default function StreamingText( { streamUrl, text = '', typingSpeed = 30, as = 'div', ...rest } ) {
	const [ rendered, setRendered ] = useState( '' );
	const [ isStreaming, setIsStreaming ] = useState( true );
	const cancelled = useRef( false );

	useEffect( () => {
		cancelled.current = false;
		setRendered( '' );
		setIsStreaming( true );

		async function runRealStream() {
			try {
				const response = await fetch( streamUrl );
				const reader = response.body.getReader();
				const decoder = new TextDecoder();
				for ( ;; ) {
					const { done, value } = await reader.read();
					if ( done || cancelled.current ) {
						break;
					}
					setRendered( ( prev ) => prev + decoder.decode( value, { stream: true } ) );
				}
			} finally {
				if ( ! cancelled.current ) {
					setIsStreaming( false );
				}
			}
		}

		function runSimulatedTypewriter() {
			let i = 0;
			const interval = setInterval( () => {
				if ( cancelled.current || i >= text.length ) {
					clearInterval( interval );
					setIsStreaming( false );
					return;
				}
				i += 1;
				setRendered( text.slice( 0, i ) );
			}, typingSpeed );
			return () => clearInterval( interval );
		}

		if ( streamUrl ) {
			runRealStream();
			return () => {
				cancelled.current = true;
			};
		}

		return runSimulatedTypewriter();
	}, [ streamUrl, text, typingSpeed ] );

	const Tag = m[ as ] || m.div;

	return (
		<Tag layout="position" transition={ { type: 'spring', stiffness: 260, damping: 30 } } { ...rest }>
			<m.div layout>
				{ rendered }
				{ isStreaming && (
					<m.span
						aria-hidden="true"
						animate={ { opacity: [ 1, 0 ] } }
						transition={ { duration: 0.6, repeat: Infinity, repeatType: 'reverse' } }
						style={ { display: 'inline-block', marginLeft: '2px' } }
					>
						▍
					</m.span>
				) }
			</m.div>
		</Tag>
	);
}
