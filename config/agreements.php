<?php

/*
|--------------------------------------------------------------------------
| Partnership agreement terms, per package tier
|--------------------------------------------------------------------------
|
| The clauses that differ between the six partnership agreements issued by
| Amref (Diamond, Platinum, Gold, Silver, CSO and Exhibitor). Everything else
| — the preamble, payments, duration, termination, governing law and so on —
| is common to all of them and lives in resources/views/pdf/agreement.blade.php.
|
| Text is transcribed from the signed-off Word templates; change it only with
| Legal's agreement. ":amount" is replaced with the package price, so a price
| change in the admin carries through without editing this file.
|
| Per tier:
|  - collaboration: section 1 is "Partner Contribution and Collaboration"
|    rather than "Partner Contribution".
|  - contribution_clauses: further clauses in section 1, after the amount.
|  - amref_roles / partner_roles: clauses 3.1 and 3.2.
|  - indemnity: whether the agreement carries the indemnity clause.
|
*/

return [

    // "…shall hold the 7th edition in 2027" — the year comes from the conference.
    'edition' => '7th',

    'amref_address' => 'P.O. Box 27691- 00506 Nairobi',

    'governing_law' => 'the Laws of Kenya',

    'tiers' => [

        'diamond' => [
            'collaboration' => true,
            'contribution_clauses' => [],
            'amref_roles' => [
                'Conduct of the Conference in a professional manner using all due skill, care and diligence, and in conformity with the Applicable Laws;',
                'Provide a dedicated point person to support and manage partner engagement in the lead-up to the conference;',
                'Issue 12 complimentary conference tickets (5 VIP- with personalized delivery options and 7 standard) to the Partner;',
                'Provide complimentary translation and live streaming services for the partner-led session;',
                'Allow the partner a maximum of 2 passes to access the Partner and VIP Lounge;',
                'Ensure the Partner is recognized during the opening and/or closing ceremony and on the final conference report;',
                'Avail a standard 6m by 3m exhibition booth for the Partner;',
                'Ensure the Partner is mentioned, on its social media mentions and ensure visibility of the Partner on the conference website and all conference material;',
                'Grant access to the Partner to the on-site media press room for partner announcements;',
                'Grant access to the Partner to the dedicated Partner registration desk and ensure Registration of the Partner and or their representatives during the conference; and',
                'Ensure branding for the Partner in conference main halls and grant the Partner an opportunity to play a video clip (60 seconds) to be shown pre and post plenary sessions.',
            ],
            'partner_roles' => [
                'Pay for the Diamond Package (:amount);',
                'Identify delegates to be sponsored to the conference;',
                'Host 2 partner-led parallel sessions with livestream and complimentary translation services;',
                'Conduct one live media interview or a co-authored opinion piece;',
                'Participate in high-level round-table discussion (2 passes to be granted) and invite only Partner reception;',
                'Exhibit relevant material at the assigned exhibition booth.',
            ],
            'indemnity' => true,
        ],

        'platinum' => [
            'collaboration' => true,
            'contribution_clauses' => [],
            'amref_roles' => [
                'Conduct of the Conference in a professional manner using all due skill, care and diligence, and in conformity with the Applicable Laws;',
                'Provide a dedicated point person to support and manage partner engagement in the lead-up to the conference;',
                'Issue 10 complimentary conference tickets (5 VIP- with personalized delivery options and 5 Standard) to the Partner;',
                'Provide complimentary translation and live streaming services for the partner-led session;',
                'Allow the partner a maximum of 2 passes to access the Partner and VIP Lounge;',
                'Ensure the Partner is recognized during the opening and/or closing ceremony and on the final conference report;',
                'Avail a standard 5m by 3m exhibition booth for the Partner;',
                'Ensure the Partner is mentioned, on its social media mentions and ensure visibility of the Partner on the conference website and all conference material;',
                'Grant access to the Partner to the on-site media press room for partner announcements;',
                'Grant access to the Partner to the dedicated Partner registration desk and ensure Registration of the Partner and or their representatives during the conference; and',
                'Ensure branding for the Partner in conference main halls and grant the Partner an opportunity to play a video clip (60 seconds) to be shown pre and post plenary sessions.',
            ],
            'partner_roles' => [
                'Pay for the Platinum Package (:amount);',
                'Identify delegates to be sponsored to the conference;',
                'Host 2 partner-led parallel sessions with livestream and complimentary translation services;',
                'Conduct one live media interview or a co-authored opinion piece;',
                'Participate in high-level round-table discussion (2 passes to be granted) and invite only Partner reception;',
                'Exhibit relevant material at the assigned exhibition booth.',
            ],
            'indemnity' => true,
        ],

        'gold' => [
            'collaboration' => true,
            'contribution_clauses' => [
                'Amref shall use the Partner Contribution exclusively for the Conference.',
                'Both parties shall make every effort to collaborate and ensure the achievement of the objectives of this Agreement through mutual recognition of the respective roles of either party on the basis of equality and mutual respect.',
            ],
            'amref_roles' => [
                'Conduct of the Conference in a professional manner using all due skill, care and diligence, and in conformity with the Applicable Laws;',
                'Issue 8 Complimentary conference tickets (3 VIP, 5 Standard);',
                'Allow the partner to access high-level round table discussions (1 pass) and invite only Partner receptions;',
                'Grant access of and VIP Lounge (1 pass);',
                'Recognize the Partner during opening and closing ceremony and the official conference report;',
                'Avail a standard 6m by 3m exhibition booth for the Partner;',
                'Ensure the Partner is mentioned where applicable, on its social media mentions;',
                'Ensure visibility of the Partner on the conference website and all conference material;',
                'Grant access to on-site media press room for the Partner’s announcements; and',
                'Grant access to the Partner to the dedicated Partner registration desk and ensure Registration of partners and or their representatives during the conference.',
            ],
            'partner_roles' => [
                'Pay for the Gold Package (:amount);',
                'Identify delegates to be sponsored to the conference;',
                'Host a partner-led parallel session with complimentary translation services;',
                'Conduct one live media interview or a co-authored opinion piece;',
                'Participate in 1 high-level round table discussion and invite only Partner receptions; and',
                'Exhibit relevant material at the assigned exhibition booth.',
            ],
            'indemnity' => true,
        ],

        'silver' => [
            'collaboration' => false,
            'contribution_clauses' => [],
            'amref_roles' => [
                'Conduct of the Conference in a professional manner using all due skill, care and diligence, and in conformity with the Applicable Laws;',
                'Issue 5 complimentary conference tickets;',
                'Provide complimentary translation and live streaming services for the partner-led session;',
                'Allow the partner to access the partner lounge (2 passes) and the invite only Partner receptions (2 passes);',
                'Avail a standard 3m by 3m exhibition booth for the Partner;',
                'Ensure the Partner is mentioned, on its social media mentions and ensure visibility of the Partner on the conference website and on the final conference report;',
                'Grant access to the Partner to the dedicated Partner registration desk and ensure Registration of partners and or their representatives during the conference; and',
                'Allow Partner to display a digital banner at the conference center.',
            ],
            'partner_roles' => [
                'Pay for the Silver Package (:amount);',
                'Identify delegates to be sponsored to the conference;',
                'Host a partner-led parallel session with complimentary translation services;',
                'Exhibit relevant material at the assigned exhibition booth.',
            ],
            'indemnity' => true,
        ],

        'cso' => [
            'collaboration' => false,
            'contribution_clauses' => [],
            'amref_roles' => [
                'Conduct of the Conference in a professional manner using all due skill, care and diligence, and in conformity with the Applicable Laws;',
                'Issue 3 regular complimentary conference tickets to the Partner;',
                'Provide complimentary translation and live streaming services for the partner-led session;',
                'Allow the partner to access the invite only Partner receptions;',
                'Avail a standard 3m by 3m exhibition booth for the Partner;',
                'Ensure the Partner is mentioned, on its social media mentions;',
                'Ensure visibility of the Partner on the conference website and all conference material;',
                'Grant access to the Partner to the dedicated Partner registration desk and ensure Registration of partners and or their representatives during the conference.',
            ],
            'partner_roles' => [
                'Pay for the CSO Package (:amount);',
                'Identify the Global South CSO or delegates to be sponsored to the conference;',
                'Host a partner-led parallel session with complimentary translation services;',
                'Participate in a high-level round-table discussion and invite only Partner reception; and partner lounge.',
                'Exhibit relevant material at the assigned exhibition booth.',
            ],
            'indemnity' => false,
        ],

        'exhibitor' => [
            'collaboration' => false,
            'contribution_clauses' => [],
            'amref_roles' => [
                'Conduct of the Conference in a professional manner using all due skill, care and diligence, and in conformity with the Applicable Laws;',
                'Issue 2 regular complimentary conference tickets to the Partner;',
                'Provide complimentary translation and live streaming services for the partner-led session;',
                'Allow the partner to access the invite only Partner receptions;',
                'Avail a standard 3m by 3m exhibition booth for the Partner;',
                'Ensure the Partner is mentioned, on its social media mentions;',
                'Ensure visibility of the Partner on the conference website and all conference material;',
                'Grant access to the Partner to the dedicated Partner registration desk and ensure Registration of partners and or their representatives during the conference.',
            ],
            'partner_roles' => [
                'Pay for the Exhibitor Partnership Package (:amount);',
                'Identify the delegates to be sponsored to the conference;',
                'Host a partner-led parallel session with complimentary translation services;',
                'Participate in a high-level round-table discussion and invite only Partner reception; and partner lounge.',
                'Exhibit relevant material at the assigned exhibition booth.',
            ],
            'indemnity' => false,
        ],

    ],
];
