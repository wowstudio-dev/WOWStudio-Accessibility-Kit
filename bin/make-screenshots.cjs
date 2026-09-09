/**
 * Captures the WordPress.org screenshots from a running wp-env site.
 *
 * Real screens rather than mockups. The directory listing is where somebody
 * decides whether to install this, and a composed picture of an interface that
 * does not exist is the same species of claim the plugin is built not to make.
 *
 * Checked in so the set can be retaken when a screen changes, in the same
 * order, at the same size, without anybody having to remember which seven
 * screens they were or how the chrome was hidden.
 *
 * Development-only. Needs `npx wp-env start` running with content to scan, and
 * Playwright driving the Chrome installed on the machine.
 *
 *     node bin/make-screenshots.cjs
 */
const { chromium } = require( 'playwright' );
const path = require( 'path' );

const DIR = path.join( __dirname, '..', '.wordpress-org' );
const SITE = 'http://localhost:8888';
const WIDTH = 1400;

// WordPress's own furniture. Every directory screenshot that includes the admin
// menu and the toolbar is mostly a picture of WordPress.
const CHROME_OFF = `
	#adminmenumain, #wpadminbar, #wpfooter, .notice, .update-nag { display: none !important; }
	#wpcontent { margin-left: 0 !important; padding-left: 0 !important; }
	html.wp-toolbar { padding-top: 0 !important; }
`;

// The order here is the order in readme.txt's Screenshots section. They have to
// agree: WordPress.org numbers the files and reads the captions positionally.
const SHOTS = [
	{ file: 'screenshot-1.png', url: '/wp-admin/admin.php?page=wowstudio-accessibility-kit', wait: '.wsak-overview__stats' },
	{ file: 'screenshot-2.png', url: '/wp-admin/admin.php?page=wsak-scan-fix', wait: '.wsak-picker', open: 'result' },
	{ file: 'screenshot-3.png', url: '/wp-admin/admin.php?page=wsak-scan-fix', wait: '.wsak__nav', tab: 'Your content' },
	{ file: 'screenshot-4.png', url: '/wp-admin/admin.php?page=wsak-scan-fix', wait: '.wsak__nav', tab: 'Images' },
	{ file: 'screenshot-5.png', url: '/wp-admin/admin.php?page=wsak-settings', wait: '.wsak-fixes__list' },
	{ file: 'screenshot-6.png', url: '/wp-admin/admin.php?page=wsak-statement', wait: '.wsak-statement-panel' },
	{ file: 'screenshot-7.png', url: '/wp-admin/admin.php?page=wowstudio-accessibility-kit&welcome=1', wait: '.wsak-welcome__stage' },
];

( async () => {
	const browser = await chromium.launch( { channel: 'chrome', headless: true } );
	const page = await browser.newPage( { viewport: { width: WIDTH, height: 1000 } } );

	await page.goto( SITE + '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await Promise.all( [ page.waitForLoadState( 'networkidle' ), page.click( '#wp-submit' ) ] );

	for ( const shot of SHOTS ) {
		await page.setViewportSize( { width: WIDTH, height: 1000 } );
		await page.goto( SITE + shot.url, { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: CHROME_OFF } );

		try {
			await page.waitForSelector( shot.wait, { timeout: 15000 } );
		} catch ( e ) {
			console.log( `  ! ${ shot.file }: ${ shot.wait } never appeared` );
		}

		if ( shot.tab ) {
			await page.getByRole( 'button', { name: shot.tab, exact: true } ).click();
			await page.waitForTimeout( 2500 );
		}

		if ( shot.open === 'result' ) {
			await page
				.locator( '.wsak a, .wsak button' )
				.filter( { hasText: /Scored .* out of/ } )
				.first()
				.click();
			await page.waitForSelector( '.wsak-result', { timeout: 20000 } );
			await page.waitForTimeout( 4000 );
		}

		await page.waitForTimeout( 1500 );

		const box = await page.evaluate( () => {
			const wrap = document.querySelector( '.wsak' );
			const foot = document.querySelector( '.wsak__footer' ) || wrap;
			return { bottom: foot.getBoundingClientRect().bottom + window.scrollY };
		} );

		/*
		 * The viewport is grown to the content before the capture rather than
		 * the element being screenshotted where it stands. An element taller
		 * than the viewport composites wrongly — the first attempt at this came
		 * out painted into one quadrant with the rest blank.
		 *
		 * One width and one height cap for the whole set: the directory scales
		 * every screenshot into the same column, and seven images of seven
		 * shapes read as seven unrelated things.
		 */
		const height = Math.min( 1600, Math.ceil( box.bottom ) + 24 );
		await page.setViewportSize( { width: WIDTH, height } );
		await page.waitForTimeout( 900 );

		await page.screenshot( {
			path: `${ DIR }/${ shot.file }`,
			clip: { x: 0, y: 0, width: WIDTH, height },
		} );
		console.log( `${ shot.file }  ${ WIDTH }x${ height }` );
	}

	await browser.close();
} )();
