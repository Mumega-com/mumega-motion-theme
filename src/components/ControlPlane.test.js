import { readControlPlaneConfig } from './ControlPlane';

function createControlPlane( extra = '' ) {
	document.body.innerHTML = `
		<section data-mcpwp-control-plane>
			<svg aria-hidden="true"></svg>
			<a href="#operate" data-mcpwp-intent="operate" data-mcpwp-summary="Operate safely" data-mcpwp-action-label="Start one site" data-mcpwp-action-href="/start" data-mcpwp-accent="violet" data-mcpwp-event="homepage_intent_selected"><strong>Operate</strong></a>
			<a href="#scale" data-mcpwp-intent="scale" data-mcpwp-summary="Scale safely" data-mcpwp-action-label="See agency" data-mcpwp-action-href="/agency" data-mcpwp-accent="teal" data-mcpwp-event="homepage_intent_selected"><strong>Scale</strong></a>
			<a href="#understand" data-mcpwp-intent="understand" data-mcpwp-summary="Understand first" data-mcpwp-action-label="Read the guide" data-mcpwp-action-href="/guide" data-mcpwp-accent="cobalt" data-mcpwp-event="homepage_intent_selected"><strong>Understand</strong></a>
			${ extra }
			<aside data-mcpwp-control-summary aria-live="polite">
				<p class="mcpwp-control-home__eyebrow">CURRENT ROUTE</p>
				<p><strong>Choose the work first.</strong></p>
				<p>Every path keeps WordPress central.</p>
			</aside>
		</section>
	`;

	return document.querySelector( '[data-mcpwp-control-plane]' );
}

describe( 'MCPWP control-plane server contract', () => {
	test( 'derives every route and initial state from semantic server markup', () => {
		const config = readControlPlaneConfig( createControlPlane(), '#scale' );

		expect( config.routes ).toEqual( [
			{
				id: 'operate',
				label: 'Operate',
				summary: 'Operate safely',
				actionLabel: 'Start one site',
				actionHref: '/start',
				accent: 'violet',
				eventName: 'homepage_intent_selected',
			},
			{
				id: 'scale',
				label: 'Scale',
				summary: 'Scale safely',
				actionLabel: 'See agency',
				actionHref: '/agency',
				accent: 'teal',
				eventName: 'homepage_intent_selected',
			},
			{
				id: 'understand',
				label: 'Understand',
				summary: 'Understand first',
				actionLabel: 'Read the guide',
				actionHref: '/guide',
				accent: 'cobalt',
				eventName: 'homepage_intent_selected',
			},
		] );
		expect( config.initialIntent ).toBe( 'scale' );
		expect( config.defaultSummary ).toEqual( {
			eyebrow: 'CURRENT ROUTE',
			title: 'Choose the work first.',
			description: 'Every path keeps WordPress central.',
		} );
		expect( config.staticHTML ).toContain( '<svg aria-hidden="true"></svg>' );
		expect( config.staticHTML ).not.toContain( 'data-mcpwp-control-summary' );
	} );

	test( 'ignores malformed routes and refuses to replace fallback without a valid route', () => {
		document.body.innerHTML = `
			<section data-mcpwp-control-plane>
				<a href="/wrong" data-mcpwp-intent="operate" data-mcpwp-summary="Summary" data-mcpwp-action-label="Action" data-mcpwp-action-href="/start"><strong>Operate</strong></a>
				<aside data-mcpwp-control-summary><p><strong>Fallback</strong></p></aside>
			</section>
		`;

		expect(
			readControlPlaneConfig(
				document.querySelector( '[data-mcpwp-control-plane]' ),
				'#operate'
			)
		).toBeNull();
	} );

	test( 'does not accept an unknown URL fragment as selected state', () => {
		const config = readControlPlaneConfig(
			createControlPlane(),
			'#not-a-real-path'
		);

		expect( config.initialIntent ).toBe( '' );
	} );
} );
