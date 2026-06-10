<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06163a">
    <meta name="description" content="Lagos Padel Club is coming soon. A new home for padel, community, and competition in Lagos.">
    <title>Lagos Padel Club | Coming Soon</title>
    <style>
        :root {
            --navy: #06163a;
            --navy-light: #0c295b;
            --yellow: #ffd400;
            --blue: #25a8ee;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            background: var(--navy);
        }

        body {
            min-height: 100svh;
            margin: 0;
            color: var(--white);
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 82% 18%, rgba(37, 168, 238, .16), transparent 27rem),
                radial-gradient(circle at 14% 82%, rgba(255, 212, 0, .12), transparent 25rem),
                linear-gradient(135deg, #04102d, var(--navy) 52%, #03102d);
        }

        .page {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, .8fr);
            min-height: 100svh;
            overflow: hidden;
        }

        .page::before {
            position: absolute;
            inset: 0;
            opacity: .2;
            background-image:
                linear-gradient(rgba(255, 255, 255, .07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .07) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: linear-gradient(90deg, black, transparent 72%);
            content: "";
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 1;
            display: flex;
            min-height: 100svh;
            padding: clamp(2rem, 6vw, 6rem);
            flex-direction: column;
        }

        .mini-brand {
            display: flex;
            color: var(--yellow);
            align-items: center;
            gap: .8rem;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .22em;
            text-transform: uppercase;
        }

        .mini-brand::before {
            width: 2.6rem;
            height: 4px;
            border-radius: 99px;
            background: var(--yellow);
            content: "";
        }

        .copy {
            width: min(100%, 740px);
            margin: auto 0;
            padding: clamp(4rem, 10vh, 8rem) 0;
        }

        .eyebrow {
            margin: 0 0 1.4rem;
            color: var(--blue);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .28em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 720px;
            margin: 0;
            font-size: clamp(3.8rem, 8vw, 8.2rem);
            letter-spacing: -.075em;
            line-height: .84;
            text-transform: uppercase;
        }

        h1 span {
            display: block;
            color: var(--yellow);
            -webkit-text-stroke: 1px var(--yellow);
        }

        .intro {
            max-width: 590px;
            margin: clamp(2rem, 5vh, 3.5rem) 0 0;
            color: rgba(255, 255, 255, .7);
            font-size: clamp(1rem, 1.6vw, 1.18rem);
            line-height: 1.75;
        }

        .status {
            display: flex;
            margin-top: 2.5rem;
            align-items: center;
            gap: .8rem;
            color: var(--white);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .status::before {
            width: .7rem;
            height: .7rem;
            border-radius: 50%;
            background: var(--yellow);
            box-shadow: 0 0 0 6px rgba(255, 212, 0, .12);
            content: "";
            animation: pulse 2s ease-in-out infinite;
        }

        footer {
            display: flex;
            color: rgba(255, 255, 255, .46);
            justify-content: space-between;
            gap: 1rem;
            font-size: .64rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .visual {
            position: relative;
            z-index: 1;
            display: grid;
            min-height: 100svh;
            padding: clamp(2rem, 5vw, 5rem);
            place-items: center;
        }

        .visual::before,
        .visual::after {
            position: absolute;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 50%;
            content: "";
        }

        .visual::before {
            width: min(43vw, 660px);
            aspect-ratio: 1;
        }

        .visual::after {
            width: min(35vw, 530px);
            aspect-ratio: 1;
            border-color: rgba(255, 212, 0, .18);
        }

        .logo {
            position: relative;
            z-index: 2;
            display: block;
            width: min(100%, 610px);
            filter: drop-shadow(0 34px 50px rgba(0, 0, 0, .35));
            mix-blend-mode: screen;
        }

        @keyframes pulse {
            50% {
                box-shadow: 0 0 0 12px rgba(255, 212, 0, 0);
            }
        }

        @media (max-width: 860px) {
            .page {
                display: block;
            }

            .content {
                min-height: 100svh;
                padding: 2rem clamp(1.3rem, 7vw, 4rem);
            }

            .visual {
                min-height: 75svh;
                padding: 3rem 1.5rem;
                border-top: 1px solid rgba(255, 255, 255, .12);
            }

            .visual::before {
                width: min(88vw, 650px);
            }

            .visual::after {
                width: min(72vw, 530px);
            }
        }

        @media (max-width: 500px) {
            h1 {
                font-size: clamp(3.6rem, 19vw, 5.5rem);
            }

            footer {
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .status::before {
                animation: none;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="content">
            <header class="mini-brand">Lagos Padel Club</header>

            <div class="copy">
                <p class="eyebrow">A new court is taking shape</p>
                <h1>Game on.<span>Coming soon.</span></h1>
                <p class="intro">
                    Lagos is getting a new home for padel, competition and community.
                    We are building something special. Stay close.
                </p>
                <div class="status">Opening in Lagos</div>
            </div>

            <footer>
                <span>Lagos, Nigeria</span>
                <span>&copy; <?= date('Y') ?> Lagos Padel Club</span>
            </footer>
        </section>

        <aside class="visual" aria-label="Lagos Padel Club">
            <img class="logo" src="logo.png" alt="Lagos Padel Club, established 2025">
        </aside>
    </main>
</body>
</html>
