<?php

namespace App\Libraries;

/**
 * Thirteenth batch: functions added lately (pull tests on a Lacking row, Remove Status of a Whole Shoe test, Material Testing tabs and the
 * Lab Ref click-through to the Material Test Report) and how the assistant itself answers (how sure it is, why it picked an answer, how it
 * explains step by step). Answers follow the pattern of the best training videos: the answer first, then short steps, a worked example, then the
 * reason. Behaviour only: no password, key or threshold. Loaded by `php spark brain:seed`.
 */
class BrainSeed13
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $k = static fn (string $title, string $keywords, string $body, array $steps = [], array $extra = []): array => array_merge(['title' => $title, 'keywords' => $keywords, 'body' => $body, 'steps' => $steps], $extra);

        return [
            $k('How do I add a pull test to a bonding row (also a Lacking row)?', 'add pull test, add pull tests to bonding row, pull test lab ref no, add eyelet, add heel pull, add d-ring, add rivet, pull test on lacking row, cannot add pull test, pull test max 4, edit bonding row pull test, add pull test to lacking',
                'In the Lab Tracker, open the Bonding Shoe Collection, click Edit on the row and tick the pull types you need: Heel Pull, Eyelet, D-Ring or Rivet. Save, and each type gets its own Pull Test Lab Ref (DTP + date + HP / EY / DR / RVT + line + side) shown in that same row; no second row is created. A row can carry at most 4 pull tests, one of each type, and only an Admin, a Supervisor or a Bonding PIC can add them. This also works for a Lacking row that has no bonding report yet; the pull test is made from the row\'s style, colorway, production code and MTL #.',
                ['Open Lab Tracker > Bonding Shoe Collection and find the row (search the Lab Ref).', 'Click the pencil (Edit) button of the row.', 'Tick Heel Pull, Eyelet, D-Ring or Rivet (the types it already has are not offered again).', 'Save: the new pull Lab Ref appears in the Pull Test LabRefNo column of the same row.']),
            $k('How do I remove the status of a Whole Shoe test so it starts over?', 'remove status whole shoe, reset whole shoe test, start over whole shoe test, remove pass from whole shoe, clear completed whole shoe, redo whole shoe test, undo complete whole shoe, remove result whole shoe monitor, restart test lab reference',
                'Open the test in the Whole Shoe Test page with Open Monitor and press the red Remove Status button, then confirm. The result (Pass / Fail / Completed), timer, cycles, findings and recorded samples of that Lab Ref are deleted completely and the test goes back to Pending, so it can be started again. The Lab Tracker shows it as Pending too. It cannot be undone, so use it only when the test must really be repeated.',
                ['Whole Shoe Test > Open Monitor on the Lab Ref.', 'Press Remove Status (red button under Stop Entire Test).', 'Confirm the warning; the test is Pending again and you can press Run / Start.']),
            $k('What are the Pending, Completed and Failed tabs in Material Testing?', 'material testing tabs, pending completed failed material testing, material testing pagination, material testing categories, where are failed materials, how many materials failed, material testing page, next page material testing, material categories count',
                'Material Testing lists every material Lab Ref in three tabs. Pending means tests are still to do or running, Completed means every test is done and none failed, Failed means at least one test failed (so a material is in one tab only). The category list on the left filters all three tabs and each tab shows its own count. Each page shows 20 materials; use Prev / Next. Open a material to see its tests and press Open test sheet to enter the values.',
                ['Pick a category on the left (or All materials).', 'Choose Pending, Completed or Failed.', 'Open a material to see its tests; use Next to see more pages.']),
            $k('What happens when I click a Lab Reference in the Lab Material Test Collection?', 'click lab reference material test collection, open material test report from lab tracker, lab ref click material report, material report auto select material type, lab material test collection results, material test report from lab ref, how is the material report made',
                'Clicking a material Lab Ref in the Lab Tracker\'s Lab Material Test Collection opens its Material Test Report. For a material received with Material Receive, the report is made (or completed) from the material details and its Material Testing sheet: the right form for the material type is chosen automatically and the test results are passed on. An Admin or a Material PIC creates it; other users can open a report that already exists. A Lab Ref from the AppSheet bench log opens its existing report.',
                ['Lab Tracker > Lab Material Test Collection.', 'Click the Lab Ref.', 'The Material Test Report opens on the right form with the results filled in.']),
            $k('How sure are you, and why did you give me this answer?', 'how sure are you, are you sure, why this answer, why did you answer that, how accurate are you, confidence of your answer, why not the other answer, how do you decide, how do you choose the answer, what is your confidence, can i trust this answer, explain your answer',
                'Short answer: I show a confidence from 0 to 100. I compare your words with the topics I know (and with what I learned from earlier questions), pick the best match and compare it with the runner-up. 80 and above means a strong match, 45 to 79 a good match with some words missing, below 45 a weak guess and I say so. If two topics fit almost equally well I tell you both and answer the first, so you can correct me. A single word is never shown as certain, because it can mean several things. The thumbs-up and thumbs-down under an answer teach me which answers help.',
                ['Read the confidence and the topic title I answered.', 'If I named a second topic, tell me which one you meant.', 'Add the Lab Reference, test name or page to make the question clearer.', 'Use thumbs-down when the answer was wrong so it can be reviewed.']),
            $k('Explain step by step, in simple words, or with an example', 'explain step by step, explain simply, explain in simple words, give me an example, why does it work, i do not understand, can you explain again, break it down, walk me through, eli5, what does that mean in simple terms, show an example',
                'Tell me the topic (a test, a status, a report step) and I answer in four parts: the answer in one line, the steps in order, a small example with real numbers when there is one, and the reason why it is done that way. If my last answer was too long or too technical, say "shorter" or "simpler" and name the topic again; I keep the topic of the conversation, so a follow-up such as "why?" or "and for leather?" is about the last thing we talked about.',
                ['Name the topic or paste the Lab Reference.', 'Say what you want: steps, an example, or the reason.', 'Ask a follow-up such as "why?" for the reason behind a step.']),
            $k('Why did the assistant ask which topic I meant?', 'why do you ask which one, you gave two topics, did you mean, which one did i mean, two answers, ambiguous question, unclear question topic, you answered the first one, asked me to choose, clarify the question',
                'When two topics match your words almost equally well (for example a test method and the illustrated guide of the same method, or one-word questions like "result"), I do not pretend to be sure: I answer the best one, name the other and ask you to confirm. Reply with the exact topic name, or add one more detail (the test name, the Lab Reference or the page), and I answer that one directly.'),
        ];
    }
}
