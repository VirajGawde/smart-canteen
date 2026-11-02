<?php
/**
 * floorplan.php — the canteen floor plan, drawn from live bench data.
 *
 * One renderer, two callers:
 *   - student/canteen.php  interactive: every bench is a <button> that opens
 *                          the seat picker (js/canteen.js, unchanged)
 *   - index.php            static: the same map as plain <div>s, for visitors
 *                          who have not logged in yet
 *
 * Needs includes/functions.php (for e()) to be loaded first.
 *
 * THE LAYOUT — 84 benches in three blue "areas", exactly as drawn in the
 * reference plan:
 *
 *   +--------------------- NMIMS Canteen ----------------------+
 *   | Front Left (4x3) | Front Right (3x3) + vacant + doorway  |   <- 12 + 9
 *   +------------------------- aisle --------------------------+
 *   | Main Hall, front rows: 7 columns x 4 rows        (28)    |
 *   |                                          doorway ->      |
 *   | Main Hall, back rows:  7 columns x 5 rows        (35)    |
 *   |                                          doorway ->      |
 *   +----------------------------------------------------------+
 *
 * Bench N always sits in slot N (numbered left-to-right, top-to-bottom through
 * each block in the order above). That is deliberate: a bench never "moves"
 * on the map because another one was added or removed, and the number printed
 * on a physical bench matches its spot on screen. A slot with no bench in the
 * database is left blank; a bench numbered beyond the plan (an admin adding
 * Bench 85, say) is listed under "Additional benches" so it is never hidden.
 */

/**
 * Bench numbers per block, row by row. Row/column counts here ARE the layout;
 * the CSS only decides how wide each column is drawn.
 */
function floorplanBlocks(): array
{
    $n = 1;
    $block = function (int $rows, int $cols) use (&$n): array {
        $out = [];
        for ($r = 0; $r < $rows; $r++) {
            $row = [];
            for ($c = 0; $c < $cols; $c++) {
                $row[] = $n++;
            }
            $out[] = $row;
        }
        return $out;
    };

    return [
        'frontLeft'  => $block(3, 4),   //  1-12
        'frontRight' => $block(3, 3),   // 13-21
        'hallFront'  => $block(4, 7),   // 22-49
        'hallBack'   => $block(5, 7),   // 50-84
    ];
}

/**
 * Every bench with its seat statuses in seat order — one query, no N+1.
 * Returns rows keyed by bench_number, each with a 'seats' array of statuses.
 */
function floorplanFetchBenches(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT b.id, b.bench_number, b.location, b.status,
                GROUP_CONCAT(s.status ORDER BY s.seat_number SEPARATOR ',') AS seat_statuses
         FROM benches b
         JOIN seats s ON s.bench_id = b.id
         GROUP BY b.id
         ORDER BY b.bench_number"
    )->fetchAll();

    $valid = ['available', 'reserved', 'occupied', 'unavailable'];
    $out = [];
    foreach ($rows as $r) {
        $r['seats'] = array_map(
            fn($s) => in_array($s, $valid, true) ? $s : 'unavailable',
            explode(',', (string) $r['seat_statuses'])
        );
        $out[(int) $r['bench_number']] = $r;
    }
    return $out;
}

/**
 * One colour per bench, rolled up from its four seats: green while ANY seat is
 * still free (it can be booked), otherwise whatever is filling the rest of it,
 * or grey if the admin closed the bench. The per-seat detail is still there —
 * the four bars along the bottom of each bench show its seats one by one.
 */
function floorplanBenchState(array $b): array
{
    $counts = array_count_values($b['seats']);
    $free = $counts['available'] ?? 0;

    if ($b['status'] === 'unavailable') {
        $status = 'unavailable';
    } elseif ($free > 0) {
        $status = 'available';
    } elseif (($counts['occupied'] ?? 0) > 0) {
        $status = 'occupied';
    } elseif (($counts['reserved'] ?? 0) > 0) {
        $status = 'reserved';
    } else {
        $status = 'unavailable';
    }

    return ['status' => $status, 'free' => $free];
}

function floorplanBenchLabel(array $b, array $state): string
{
    $tail = match ($state['status']) {
        'available'   => $state['free'] . ' of ' . count($b['seats']) . ' seats free',
        'reserved'    => 'all seats reserved',
        'occupied'    => 'full',
        default       => 'closed',
    };
    return 'Bench ' . (int) $b['bench_number'] . ' — ' . ($b['location'] ?: 'Canteen') . ' — ' . $tail;
}

/** One bench: a <button> when interactive, a plain <div> otherwise. */
function floorplanBench(?array $b, array $o): string
{
    if ($b === null) {
        // A slot with no bench in the database: keep the space, draw nothing.
        return '<span class="fp-bench fp-empty" aria-hidden="true"></span>';
    }

    $state = floorplanBenchState($b);
    $label = floorplanBenchLabel($b, $state);

    $seats = '';
    foreach ($b['seats'] as $s) {
        $seats .= '<i class="fp-s-' . $s . '"></i>';
    }
    $inner = '<span class="fp-num">' . (int) $b['bench_number'] . '</span>'
           . '<span class="fp-seats">' . $seats . '</span>';

    $cls = 'fp-bench fp-' . $state['status'];

    if (!$o['interactive']) {
        return '<div class="' . $cls . '" title="' . e($label) . '">' . $inner . '</div>';
    }

    // Same contract js/canteen.js has always listened for: .bench-card with
    // data-bench-id / data-bench-number. Only benches that can actually be
    // booked are enabled — and none are while this student is banned or
    // already holds a seat (the page explains why in a banner).
    $disabled = $o['locked'] || $state['status'] !== 'available';
    return '<button type="button" class="bench-card ' . $cls . '"'
         . ' data-bench-id="' . (int) $b['id'] . '" data-bench-number="' . (int) $b['bench_number'] . '"'
         . ' title="' . e($label) . '" aria-label="' . e($label) . '"'
         . ($disabled ? ' disabled' : '') . '>' . $inner . '</button>';
}

function floorplanRow(array $numbers, array $benches, array $o, string $class): string
{
    $html = '<div class="fp-row ' . $class . '">';
    foreach ($numbers as $n) {
        $html .= floorplanBench($benches[$n] ?? null, $o);
    }
    return $html . '</div>';
}

/** Hollow arrow pointing into the hall, through an opening in the wall. */
function floorplanDoor(): string
{
    return '<div class="fp-door"><svg viewBox="0 0 120 34" width="102" height="29" aria-hidden="true" focusable="false">'
         . '<path d="M120 11H28V2L2 17l26 15v-9h92"/></svg></div>';
}

function floorplanLegend(): string
{
    $items = [
        'available'   => 'Available',
        'reserved'    => 'Reserved',
        'occupied'    => 'Occupied',
        'unavailable' => 'Unavailable',
    ];
    $html = '<ul class="fp-legend" aria-label="Floor plan legend">';
    foreach ($items as $key => $label) {
        $html .= '<li><span class="fp-swatch fp-' . $key . '"></span>' . $label . '</li>';
    }
    $html .= '<li class="fp-legend-note"><span class="fp-swatch fp-swatch-bench"><span class="fp-seats">'
           . '<i class="fp-s-available"></i><i class="fp-s-available"></i><i class="fp-s-occupied"></i><i class="fp-s-unavailable"></i>'
           . '</span></span>The 4 bars on a bench are its 4 seats</li>';
    return $html . '</ul>';
}

/**
 * @param array $benches rows from floorplanFetchBenches(), keyed by bench number
 * @param array $opts    interactive (bool)  buttons that open the seat picker
 *                       locked      (bool)  interactive, but nothing bookable
 *                                           right now (banned / already seated)
 *                       legend      (bool)  draw the legend beside the map
 *                       title       (string) text in the title bar
 */
function renderFloorplan(array $benches, array $opts = []): string
{
    $o = $opts + ['interactive' => false, 'locked' => false, 'legend' => true, 'title' => 'NMIMS Canteen'];
    $b = floorplanBlocks();
    $row = fn(array $nums, string $cls) => floorplanRow($nums, $benches, $o, $cls);

    $h  = '<div class="fp ' . ($o['interactive'] ? 'fp-live' : 'fp-static') . ($o['interactive'] && $o['locked'] ? ' fp-locked' : '') . '">';
    $h .= '<div class="fp-layout"><div class="fp-scroll"><div class="fp-map">';

    $h .= '<div class="fp-title">' . e($o['title']) . '</div>';

    // --- top: the two front areas, side by side ---------------------------
    $h .= '<div class="fp-top">';
    $h .= '<div class="fp-front-left" role="group" aria-label="Front Left area">';
    foreach ($b['frontLeft'] as $r) {
        $h .= $row($r, 'fp-row-4');
    }
    $h .= '</div><div class="fp-divider"></div>';
    $h .= '<div class="fp-front-right" role="group" aria-label="Front Right area"><div class="fp-fr-grid">';
    foreach ($b['frontRight'] as $r) {
        $h .= $row($r, 'fp-row-3');
    }
    $h .= '</div><div class="fp-fr-side"><div class="fp-vacant"></div>' . floorplanDoor() . '</div></div>';
    $h .= '</div>';

    // --- aisle, with an opening between the front and the hall -----------
    $h .= '<div class="fp-aisle"><span></span><span class="fp-aisle-open"></span><span></span></div>';

    // --- the main hall -----------------------------------------------------
    $h .= '<div class="fp-hall" role="group" aria-label="Main Hall">';
    foreach ($b['hallFront'] as $r) {
        $h .= $row($r, 'fp-row-7');
    }
    $h .= '<div class="fp-gap">' . floorplanDoor() . '</div>';
    foreach ($b['hallBack'] as $r) {
        $h .= $row($r, 'fp-row-7');
    }
    $h .= '<div class="fp-gap fp-gap-end">' . floorplanDoor() . '</div>';
    $h .= '</div>';

    // --- benches numbered beyond the plan (never hide a bookable bench) ----
    $inPlan = [];
    foreach ($b as $block) {
        foreach ($block as $r) {
            foreach ($r as $n) {
                $inPlan[$n] = true;
            }
        }
    }
    $extra = array_diff_key($benches, $inPlan);
    if ($extra) {
        $h .= '<div class="fp-extra"><div class="fp-extra-title">Additional benches</div><div class="fp-row fp-row-extra">';
        foreach ($extra as $bench) {
            $h .= floorplanBench($bench, $o);
        }
        $h .= '</div></div>';
    }

    $h .= '</div></div>';   // .fp-map, .fp-scroll
    if ($o['legend']) {
        $h .= floorplanLegend();
    }
    $h .= '</div>';         // .fp-layout
    if ($o['interactive']) {
        $h .= '<div class="fp-hint">Swipe sideways to see the whole hall</div>';
    }
    return $h . '</div>';
}
