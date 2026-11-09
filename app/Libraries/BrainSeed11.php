<?php

namespace App\Libraries;

/**
 * Eleventh batch: what was added to the InterLink eTRF page, the InterLink service and the layout after BrainSeed10 - the Order Summary
 * lookup, the InterLink pick lists, the service address choice (.43 / .45), the two InterLink accounts, the installer, the Report
 * Submission grid, the collapsed sidebar and the automatic start of the AI service. Behaviour only: no password, no key, no threshold.
 * Loaded by `php spark brain:seed`; the keywords of every entry are also the phrases `php spark brain:train-new` trains on.
 */
class BrainSeed11
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $o  = static fn (string $label, string $url): array => ['type' => 'open', 'label' => $label, 'url' => $url];
        $k  = static fn (string $title, string $keywords, string $body, array $steps = [], array $extra = []): array => array_merge(['title' => $title, 'keywords' => $keywords, 'body' => $body, 'steps' => $steps], $extra);
        $et = ['actions' => [$o('Open VF eTRF', 'interlink/etrf')], 'page_match' => 'interlink'];
        $st = ['actions' => [$o('Open Settings', 'settings')]];

        return [
            $k('What is the Order Summary lookup on the eTRF page?',
                'order summary, order summary pdf, mtl season lookup, season from mtl, find season of mtl, mtl running styles, season ticks f26 h26 s27, order summary folder, mtl list pdf, first case list',
                'On InterLink > VF eTRF, the Order Summary tab reads the PDFs in the QC share folder #ORDER SUMMARY (the MTL running-style lists, for example "MTL List Oct 2026" and the "CRD MTL Summary and First Case" file). Type or pick an MTL# and it shows the season ticks (F26 / H26 / S27), the first season, style, colour, risk level, note, remark and cutting die number. Click a season button to put it into the Season field and every row. A season is filled on its own only when the form has none and the PDFs show exactly one.',
                ['Open InterLink > VF eTRF.', 'Pick a Lab Reference, or type the MTL# in the Order Summary tab and press Look up season.', 'If several seasons are ticked, click the one this request is for.'], $et),
            $k('A new Order Summary PDF was added: how does NEXUS-L pick it up?',
                'new order summary pdf, pdf updated order summary, order summary refresh, realtime order summary, upload new mtl list, pic uploaded new pdf, order summary not updating, update season list',
                'Nothing has to be synced. Every lookup lists the folder and re-reads only the PDFs whose size or modified time changed; a new or replaced PDF is searchable on the next lookup (the first read of a changed PDF takes a few seconds). The open eTRF page also checks again every 30 seconds while an MTL# is shown and tells you when the PDFs changed.',
                ['Put the new PDF in the #ORDER SUMMARY folder.', 'Look up the MTL# again on the eTRF page.'], $et),
            $k('The Order Summary says the MTL is not found or the folder is not reachable',
                'mtl not in order summary, order summary not found, order summary folder not reachable, cannot reach order summary, order summary share login, order summary python error, mtl missing season',
                'An MTL# that is in none of the PDFs shows "not in the Order Summary PDFs". "Cannot reach the folder" means the web server cannot open \\\\192.168.32.130\\qc lab files\\#ORDER SUMMARY: a service session does not share your Windows login. Set a share user and password under Settings > Defaults (Order Summary share user / password), or run the web server under an account that can open the folder. The PDFs are read with Python (PyMuPDF), so Python must be installed for the web server\'s user.',
                ['Open Settings > Defaults.', 'Check the Order Summary PDF folder path.', 'Enter the Order Summary share user and password if the server cannot open the folder.'], $st),
            $k('What are the InterLink pick lists on the eTRF page?',
                'interlink pick lists, pick list search button, supplier name list, facility list, interlink search button values, read pick lists from interlink, supplier facility search, interlink lov lists, dropdown values interlink',
                'Every field in InterLink with a search button (Supplier / Facility, Season, Brand, Country of Origin, Fiber Content, End Use, Test Package, GB codes, Test Item Name ...) offers a fixed list. The button "Read pick lists from InterLink" on the eTRF page makes the background service open each of the five templates, read every page of every list and send them to NEXUS-L; lists that depend on another field (End Use and Brand depend on Product Category, Test Package on Brand, Test Stage on Service Test Type) are read once per value of that parent. Nothing is saved or submitted in InterLink. The "InterLink pick lists" tab then searches the Supplier / Facility list and applies the Facility ID, Facility Name, Supplier ID and Supplier Name to the form.',
                ['Open InterLink > VF eTRF.', 'In the InterLink pick lists tab press Read pick lists from InterLink (it takes a while).', 'Type part of a supplier or facility name and press Use on the row.'], $et),
            $k('How do I choose where the InterLink payload is sent: .43 or .45?',
                'service address 43 or 45, send payload to 45, change service ip, interlink service address, send to another computer, switch service server, payload target, interlink service url',
                'Settings > Service has "Send the payload to" with three choices: 43 (the address saved for the .43 computer), 45 (the one saved for the .45 computer) and custom (the free address further down). The next job goes to the one chosen; jobs already waiting stay in the queue of the service that took them. The Test connection button uses the chosen address. Only one service address is used at a time, and the chosen service needs both InterLink accounts signed in and the same shared key as NEXUS-L.',
                ['Open Settings > Service.', 'Set Send the payload to: 43, 45 or custom.', 'Check the addresses and press Test connection.'], $st),
            $k('Which InterLink account sends which eTRF form?',
                'which interlink account, dtp qcasst account, dtp lab account, pull test account, account for material etrf, account for product etrf, two interlink accounts, danica agbuya account, faye ann elaysa account, login interlink account service',
                'Two accounts send the forms. dtp_qcasst@dtshoes.com (Danica Agbuya) sends VF eTRF - Material, Product, bonding and Product WHOLE SHOE TEST. dtp_lab@dtshoes.com (Faye Ann Elaysa) sends VF eTRF - PULLTESTTemplate. Sign each one in once from the service tray menu (Login: dtp_qcasst / Login: dtp_lab); the sessions are kept by the service. Before opening a form the service reads the signed-in InterLink user name and refuses to send when it does not match the account the form is meant for.',
                ['Open the NexusInterlinkService tray menu.', 'Click Login: dtp_qcasst@dtshoes.com and sign in.', 'Click Login: dtp_lab@dtshoes.com and sign in.'], $et),
            $k('How do the five eTRF forms differ in InterLink?',
                'etrf forms differ, material form vs product form, bonding template, whole shoe template, pull test template, saved template etrf, template test package, which form is which, product based templates',
                'VF eTRF - Material is its own form with Material Information, Fiber Composition and Season. VF eTRF - Product is the base finished-product form. bonding, PRODUCT WHOLE SHOE TEST and PULLTESTTemplate are saved templates of the Product form that pre-fill the Test Package (VANS Bonding Strength, VANS Whole Shoe Performance, VANS Pull Strength), In-House Lab Testing, Production, Vans, Footwear, Philippines, Key Trade and Datian Subic. A template\'s saved Product ID (MTL#) is cleared by the service so it never carries into another request.',
                [], $et),
            $k('How do I install the NexusInterlinkService on another computer?',
                'install interlink service, nexus interlink service installer, setup exe service, install service on 45, service says install net 9, dotnet 9 error service, service installer download, set up interlink service',
                'Run NexusInterlinkService-Setup-<version>.exe. It includes .NET, so nothing else has to be installed; the computer needs Google Chrome or Microsoft Edge (or Install-Browser.cmd). Open the tray icon > Settings: set the IP and port (the computer\'s address or 0.0.0.0, port 5077), the shared key (the same as in NEXUS-L Settings > Service) and the NEXUS-L address. Then click Login for both InterLink accounts. Allow port 5077 in the firewall, and make sure the computer can open the NEXUS-L address because the service reports each result back there.',
                ['Run the installer.', 'Open the tray icon > Settings and set address, port, shared key and NEXUS-L address.', 'Sign in both InterLink accounts.', 'In NEXUS-L Settings > Service choose 43 or 45 and press Test connection.'], $st),
            $k('Submit fails with "window.__nx[n] is not a function"',
                'window nx is not a function, etrf not submitted typeerror, submit etrf error function, print and submit error, etrf submit failed service, advance submit step error, nx not a function',
                'The service tried a page step that its installed version does not have (the Print & Submit confirmation step was added in service version 1.2.3). Install NexusInterlinkService-Setup-1.2.3 or later on the computer where the service runs and restart it. Save as draft does not use that step.',
                ['Install the newest NexusInterlinkService setup.', 'Restart the service and send the eTRF again.'], $et),
            $k('How do I add several materials from the DSR / CFM file into the eTRF?',
                'add several materials etrf, dsr component to next row, material information rows, use component row b, add second component, multiple dsr components, material rows a b c, cfm component use button',
                'In the DSR / CFM files tab load the file, then press Use on a component: it goes into the first Material Information row that is still empty (A, then B, ...). When every row is filled a new row is added, and the same component (same material code and colour) is not added twice. Add a row yourself with + Add Material Information when you want an empty row first.',
                ['Open the DSR / CFM files tab and search the MTL#.', 'Press Use on the first component, then on the next one.'], $et),
            $k('What changed on the Report Submission page?',
                'report submission page, interlink applications grid, applications grid layout, grid rows one line, hover full value grid, interlink grid headings stay, report submission view, applications table narrow',
                'The InterLink Applications grid now shows one line per row (a cut value shows in full when you hover over it), keeps its headings and search row in view while you scroll, shows the E / U / upload buttons side by side and keeps Application No. near the left. On tablets and phones the action columns are narrower, the toolbar and page numbers wrap, and the top bar stays on one line.',
                [], ['actions' => [$o('Open Report Submission', 'interlink')]]),
            $k('How does the collapsed sidebar work?',
                'collapsed sidebar, sidebar icons only, sidebar flyout, submenu in collapsed sidebar, lab documents numbers sidebar, icon with number under, sidebar group arrow collapsed, interlink submenu flyout',
                'In the collapsed sidebar every item is an icon. A group such as InterLink or Analytics opens its sub-menu as a small panel beside the icon when you hover over it or tab to it. Lab documents show the file icon with their number below (101, 102 ... 129; 110F, 110M, 114F, 114T for the split registers; short initials for the Other documents) and the full name as a tooltip. A group\'s icon is highlighted when you are on one of its pages.',
                []),
            $k('The AI Brain does not turn on or the AI service is not running',
                'ai brain not turning on, ai brain off, ai service not running, python not running, ai service offline, start ai service, run ai cmd, ai service auto start, brain not connected, port 8765 down',
                'The AI assistant service (Python, port 8765, with PostgreSQL 5440 and Phoenix 6006) is started by ai-service\\run-ai.ps1. The Server HTML Handler keeps it running: it checks http://127.0.0.1:8765/health every 30 seconds and starts the stack when it does not answer (for example after the computer was restarted), so the handler tray program must be open. The AI Brain switch also starts it and now waits for it to answer. Models take several seconds to load, so give it a moment. To start it by hand run run-ai.cmd in the NEXUS-L folder and open http://127.0.0.1:8765/health.',
                ['Check that the Server HTML Handler is running (tray icon).', 'Open http://127.0.0.1:8765/health.', 'If it does not answer, run run-ai.cmd in the NEXUS-L folder, then click the AI Brain switch again.'], $st),
            $k('How does the eTRF page get the season of an MTL#?',
                'etrf season from mtl, which season for this mtl, auto season etrf, season field etrf, season s27 etrf, mtl season check, season fill etrf, dsr season vs order summary',
                'Two places give a season: the DSR / CFM file header and the Order Summary PDFs (ticked F26 / H26 / S27 columns, or the Season column on the First Case list). Pick a Lab Reference or search the MTL#; the Order Summary tab shows every list the MTL# is in with a button per season. Press the season this request is for. NEXUS-L never guesses between several seasons.',
                [], $et),
            $k('The eTRF supplier or factory is not accepted by InterLink',
                'supplier not accepted interlink, facility id wrong etrf, supplier name must match interlink, vendor id etrf, factory id etrf, supplier list interlink, use interlink supplier list, supplier name not in list',
                'InterLink only accepts a supplier and facility from its own list. Read the lists with "Read pick lists from InterLink", then use the InterLink pick lists tab: search the supplier or facility name or ID and press Use. That fills Facility ID, Facility Name, Supplier ID and Supplier Name exactly as InterLink writes them (for example OWN SOURCE SUPPLIER, G00000 / G00000000000F001).',
                ['Open the InterLink pick lists tab.', 'Search the supplier or facility and press Use.'], $et),
        ];
    }
}
