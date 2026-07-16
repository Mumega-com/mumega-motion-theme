<?php
/**
 * Minimal real HTTP streaming endpoint for the StreamingText demo.
 *
 * Deliberately outside WordPress's own request lifecycle — WP's REST API
 * builds a full WP_REST_Response object before sending anything, so it can't
 * do true chunked output. This file is hit directly by the browser and
 * flushes real chunks with real delays, so StreamingText's fetch +
 * ReadableStream code path is exercised against genuine streamed HTTP, not
 * a simulated/typewriter effect.
 *
 * A production version of this pattern is exactly how an actual LLM
 * response stream (OpenAI/Anthropic/etc.) reaches the browser — this file
 * stands in for that with a canned sentence, chunked the same way.
 */

header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-cache' );
header( 'X-Accel-Buffering: no' ); // Disable nginx proxy buffering, if present.

while ( ob_get_level() > 0 ) {
	ob_end_flush();
}

$sentence = 'This text is arriving from a real HTTP stream, one chunk at a time, the same way an actual AI response reaches the browser — not a client-side typewriter effect pretending to stream.';
$words    = explode( ' ', $sentence );

foreach ( $words as $word ) {
	echo $word . ' ';
	if ( ob_get_level() > 0 ) {
		ob_flush();
	}
	flush();
	usleep( 90000 ); // 90ms between words.
}
