<?php

/**
 * Content for the backoffice "field guide" (help.php): what every status, column
 * and button in the app means, in plain language, per module. Status labels and
 * field/action labels themselves are pulled live from app/translations/*.php via
 * t() in help.php — only the explanatory sentences below are hand-written, once
 * per supported language, since they're documentation prose rather than reusable
 * UI strings.
 */
function helpGlossaryModules()
{
    return [
        [
            'id' => 'overview',
            'icon' => 'overview',
            'group' => 'nav.overview',
            'item' => 'nav.overview',
            'statuses' => [],
            'terms' => [
                ['label' => 'nav.dashboard', 'note' => [
                    'en' => 'The page you land on after signing in: today\'s upcoming pickups and returns, and anything needing attention.',
                    'fr' => 'La page qui s\'affiche après connexion : les prises en charge et retours du jour, et ce qui nécessite une attention.',
                    'ar' => 'الصفحة التي تظهر بعد تسجيل الدخول: عمليات الاستلام والإرجاع القادمة اليوم، وكل ما يحتاج إلى انتباه.',
                ]],
                ['label' => 'nav.notifications', 'note' => [
                    'en' => 'The log of customer-facing messages (confirmations, reminders) prepared and sent through the app.',
                    'fr' => 'Le journal des messages destinés aux clients (confirmations, rappels) préparés et envoyés depuis l\'application.',
                    'ar' => 'سجل الرسائل الموجهة إلى العملاء (تأكيدات، تذكيرات) التي أُعدت وأُرسلت من داخل التطبيق.',
                ]],
                ['label' => 'nav.portal_requests', 'note' => [
                    'en' => 'Booking, modification, and cancellation requests customers submitted themselves through the self-service portal, waiting on a staff decision.',
                    'fr' => 'Les demandes de réservation, modification et annulation soumises par les clients eux-mêmes via le portail libre-service, en attente d\'une décision du personnel.',
                    'ar' => 'طلبات الحجز والتعديل والإلغاء التي قدّمها العملاء بأنفسهم عبر بوابة الخدمة الذاتية، وتنتظر قراراً من الموظفين.',
                ]],
            ],
        ],
        [
            'id' => 'reservations',
            'icon' => 'rentals',
            'group' => 'nav.rentals',
            'item' => 'nav.reservations',
            'statuses' => [
                ['status' => 'quote', 'note' => [
                    'en' => 'Not a real booking yet, a price estimate that hasn\'t been confirmed.',
                    'fr' => 'Pas encore une vraie réservation, juste une estimation de prix non confirmée.',
                    'ar' => 'ليس حجزاً فعلياً بعد، بل مجرد تقدير سعر لم يُؤكَّد.',
                ]],
                ['status' => 'requested', 'note' => [
                    'en' => 'Customer asked for it through the portal; staff hasn\'t acted on it yet.',
                    'fr' => 'Le client l\'a demandée depuis le portail ; le personnel n\'a pas encore traité la demande.',
                    'ar' => 'طلبه العميل عبر البوابة؛ لم يتعامل الموظفون معه بعد.',
                ]],
                ['status' => 'confirmed', 'note' => [
                    'en' => 'Booking is locked in, vehicle and dates are reserved.',
                    'fr' => 'La réservation est verrouillée, le véhicule et les dates sont bloqués.',
                    'ar' => 'الحجز مؤكد، والمركبة والتواريخ محجوزة.',
                ]],
                ['status' => 'deposit_paid', 'note' => [
                    'en' => 'Confirmed, and the security deposit has been collected.',
                    'fr' => 'Confirmée, et le dépôt de garantie a été encaissé.',
                    'ar' => 'مؤكد، وتم تحصيل مبلغ الضمان.',
                ]],
                ['status' => 'ready', 'note' => [
                    'en' => 'Everything\'s in place for pickup, just waiting on the customer to arrive.',
                    'fr' => 'Tout est en place pour la prise en charge, il ne manque plus que l\'arrivée du client.',
                    'ar' => 'كل شيء جاهز للاستلام، ولم يبقَ سوى وصول العميل.',
                ]],
                ['status' => 'active', 'note' => [
                    'en' => 'The car is out with the customer right now.',
                    'fr' => 'Le véhicule est actuellement chez le client.',
                    'ar' => 'المركبة حالياً بحوزة العميل.',
                ]],
                ['status' => 'completed', 'note' => [
                    'en' => 'Car\'s back, rental is finished and closed out.',
                    'fr' => 'Le véhicule est revenu, la location est close.',
                    'ar' => 'عادت المركبة، وانتهى التأجير وأُغلق.',
                ]],
                ['status' => 'cancelled', 'note' => [
                    'en' => 'Booking was called off before it happened.',
                    'fr' => 'La réservation a été annulée avant d\'avoir lieu.',
                    'ar' => 'أُلغي الحجز قبل أن يتم.',
                ]],
                ['status' => 'no_show', 'note' => [
                    'en' => 'Customer never turned up for pickup.',
                    'fr' => 'Le client ne s\'est jamais présenté pour la prise en charge.',
                    'ar' => 'لم يحضر العميل إطلاقاً لاستلام المركبة.',
                ]],
            ],
            'terms' => [
                ['label' => 'field.reference', 'note' => [
                    'en' => 'The booking\'s own ID, shown everywhere it\'s referenced (contracts, invoices, the planning board).',
                    'fr' => 'L\'identifiant propre de la réservation, affiché partout où elle est citée (contrats, factures, planning).',
                    'ar' => 'معرّف الحجز نفسه، يظهر في كل مكان يُشار فيه إليه (العقود، الفواتير، لوحة التخطيط).',
                ]],
                ['label' => 'nav.planning', 'note' => [
                    'en' => 'A calendar-style board showing every vehicle against time, so you can see and adjust allocations at a glance.',
                    'fr' => 'Un tableau de type calendrier montrant chaque véhicule dans le temps, pour visualiser et ajuster les affectations d\'un coup d\'œil.',
                    'ar' => 'لوحة على شكل تقويم تعرض كل مركبة عبر الزمن، لرؤية التخصيصات وتعديلها بنظرة واحدة.',
                ]],
                ['label' => 'field.pickup', 'note' => [
                    'en' => 'The exact date and time the car leaves, and the field next to it (Return) is when it comes back.',
                    'fr' => 'La date et l\'heure exactes de départ ; le champ voisin (Retour) est celui du retour.',
                    'ar' => 'التاريخ والوقت الدقيقان لخروج المركبة؛ والحقل المجاور (الإرجاع) هو وقت عودتها.',
                ]],
                ['label' => 'field.source', 'note' => [
                    'en' => 'Where the booking came from: staff entered it at the agency, or the customer requested it through the portal.',
                    'fr' => 'D\'où vient la réservation : saisie par le personnel à l\'agence, ou demandée par le client via le portail.',
                    'ar' => 'مصدر الحجز: أدخله الموظف في الوكالة، أو طلبه العميل عبر البوابة.',
                ]],
                ['label' => 'field.balance', 'note' => [
                    'en' => 'What\'s still owed on this booking after payments so far.',
                    'fr' => 'Ce qu\'il reste à payer sur cette réservation après les paiements déjà effectués.',
                    'ar' => 'المبلغ المتبقي على هذا الحجز بعد المدفوعات التي تمت حتى الآن.',
                ]],
                ['label' => 'field.options_total', 'note' => [
                    'en' => 'Add-ons (child seat, GPS, extra driver) and flat fees layered on top of the daily rate.',
                    'fr' => 'Les suppléments (siège enfant, GPS, conducteur additionnel) et frais fixes ajoutés au tarif journalier.',
                    'ar' => 'الخيارات الإضافية (مقعد طفل، نظام ملاحة، سائق إضافي) والرسوم الثابتة المضافة إلى السعر اليومي.',
                ]],
                ['label' => 'field.discount_percent', 'note' => [
                    'en' => 'A percentage knocked off the total, requires a reason on record whenever it\'s non-zero.',
                    'fr' => 'Un pourcentage déduit du total, nécessite un motif enregistré dès qu\'elle est non nulle.',
                    'ar' => 'نسبة تُخصم من الإجمالي، وتتطلب تسجيل سبب متى كانت أكبر من صفر.',
                ]],
            ],
        ],
        [
            'id' => 'contracts',
            'icon' => 'rentals',
            'group' => 'nav.rentals',
            'item' => 'nav.contracts',
            'statuses' => [
                ['status' => 'draft', 'note' => [
                    'en' => 'Generated but not yet issued, can still be freely changed.',
                    'fr' => 'Généré mais pas encore émis ; peut encore être librement modifié.',
                    'ar' => 'تم إنشاؤه لكنه لم يُصدر بعد؛ يمكن تعديله بحرية.',
                ]],
                ['status' => 'issued', 'note' => [
                    'en' => 'Locked and sent for signature, waiting on customer and/or agency acknowledgement.',
                    'fr' => 'Verrouillé et envoyé pour signature ; en attente de reconnaissance du client et/ou de l\'agence.',
                    'ar' => 'مقفل ومُرسل للتوقيع؛ بانتظار إقرار العميل و/أو الوكالة.',
                ]],
                ['status' => 'signed', 'note' => [
                    'en' => 'Both sides have acknowledged it, ready to support checkout.',
                    'fr' => 'Les deux parties l\'ont reconnu ; prêt à servir de base à la prise en charge.',
                    'ar' => 'أقرّه الطرفان، وأصبح جاهزاً لدعم عملية التسليم.',
                ]],
                ['status' => 'amended', 'note' => [
                    'en' => 'Something changed after issuing (extended dates, swapped vehicle), a new version was created.',
                    'fr' => 'Quelque chose a changé après l\'émission (dates prolongées, véhicule échangé), une nouvelle version a été créée.',
                    'ar' => 'طرأ تغيير بعد الإصدار (تمديد التواريخ، تبديل المركبة)، فتم إنشاء نسخة جديدة.',
                ]],
                ['status' => 'disputed', 'note' => [
                    'en' => 'Customer or agency has raised a disagreement over its terms.',
                    'fr' => 'Le client ou l\'agence a soulevé un désaccord sur ses termes.',
                    'ar' => 'أثار العميل أو الوكالة اعتراضاً على بنوده.',
                ]],
                ['status' => 'cancelled', 'note' => [
                    'en' => 'Voided before it took effect.',
                    'fr' => 'Rendu nul avant d\'avoir pris effet.',
                    'ar' => 'أُبطل قبل أن يدخل حيز التنفيذ.',
                ]],
            ],
            'terms' => [
                ['label' => 'field.current_version', 'note' => [
                    'en' => 'Contracts can be amended; this is which numbered version is currently in force.',
                    'fr' => 'Un contrat peut être modifié ; c\'est le numéro de la version actuellement en vigueur.',
                    'ar' => 'يمكن تعديل العقد؛ وهذا هو رقم النسخة السارية حالياً.',
                ]],
                ['label' => 'field.digest', 'note' => [
                    'en' => 'A cryptographic fingerprint of that exact version\'s content, proof it hasn\'t been silently altered after signing.',
                    'fr' => 'Une empreinte cryptographique du contenu exact de cette version, preuve qu\'il n\'a pas été modifié en silence après signature.',
                    'ar' => 'بصمة تشفيرية لمحتوى تلك النسخة بالضبط، تثبت أنه لم يُعدَّل خفيةً بعد التوقيع.',
                ]],
                ['label' => 'action.generate_contract', 'note' => [
                    'en' => 'Creates the contract document from a confirmed reservation, still a draft at this point.',
                    'fr' => 'Crée le document de contrat à partir d\'une réservation confirmée, encore un brouillon à ce stade.',
                    'ar' => 'ينشئ وثيقة العقد انطلاقاً من حجز مؤكَّد، ويبقى مسودة في هذه المرحلة.',
                ]],
                ['label' => 'action.create_amendment', 'note' => [
                    'en' => 'Opens a new version of a signed contract instead of editing it directly, keeping the original intact.',
                    'fr' => 'Ouvre une nouvelle version d\'un contrat signé au lieu de le modifier directement, en gardant l\'original intact.',
                    'ar' => 'يفتح نسخة جديدة من عقد موقّع بدلاً من تعديله مباشرة، مع الحفاظ على النسخة الأصلية كما هي.',
                ]],
            ],
        ],
        [
            'id' => 'inspections',
            'icon' => 'rentals',
            'group' => 'nav.rentals',
            'item' => 'nav.inspections',
            'statuses' => [],
            'terms' => [
                ['label' => 'option.checkout', 'note' => [
                    'en' => 'The inspection taken when the vehicle leaves with the customer.',
                    'fr' => 'L\'inspection réalisée au moment où le véhicule part avec le client.',
                    'ar' => 'الفحص الذي يُجرى عند خروج المركبة مع العميل.',
                ]],
                ['label' => 'option.return', 'note' => [
                    'en' => 'The matching inspection taken when the vehicle comes back, compared side by side against the checkout one.',
                    'fr' => 'L\'inspection équivalente réalisée au retour du véhicule, comparée côte à côte avec celle du départ.',
                    'ar' => 'الفحص المقابل الذي يُجرى عند عودة المركبة، ويُقارن جنباً إلى جنب مع فحص الخروج.',
                ]],
                ['label' => 'field.cleanliness', 'note' => [
                    'en' => 'Clean, acceptable, or dirty, one of the recorded condition checks, alongside fuel level and mileage.',
                    'fr' => 'Propre, acceptable ou sale, l\'un des contrôles d\'état enregistrés, avec le niveau de carburant et le kilométrage.',
                    'ar' => 'نظيفة أو مقبولة أو متسخة، أحد فحوصات الحالة المسجَّلة، إلى جانب مستوى الوقود والمسافة المقطوعة.',
                ]],
                ['label' => 'field.photos', 'note' => [
                    'en' => 'Six required angle shots of the vehicle; an inspection can\'t be validated without all six.',
                    'fr' => 'Six photos obligatoires du véhicule sous différents angles ; une inspection ne peut pas être validée sans les six.',
                    'ar' => 'ست صور إلزامية للمركبة من زوايا مختلفة؛ لا يمكن اعتماد الفحص دون توفرها جميعاً.',
                ]],
                ['label' => 'action.validate_inspection', 'note' => [
                    'en' => 'Locks the inspection record permanently once the required photos and signature are in, it can no longer be edited afterwards.',
                    'fr' => 'Verrouille définitivement la fiche d\'inspection une fois les photos et la signature requises fournies ; elle ne peut plus être modifiée ensuite.',
                    'ar' => 'يقفل سجل الفحص نهائياً بعد توفر الصور والتوقيع المطلوبين؛ ولا يمكن تعديله بعد ذلك.',
                ]],
                ['label' => 'field.mileage_difference', 'note' => [
                    'en' => 'Odometer reading at return minus at checkout, flags unusual distance covered.',
                    'fr' => 'Le kilométrage au retour moins celui du départ, signale une distance parcourue inhabituelle.',
                    'ar' => 'قراءة العداد عند الإرجاع ناقص قراءته عند الاستلام، تُظهر أي مسافة غير معتادة قُطعت.',
                ]],
            ],
        ],
        [
            'id' => 'customers',
            'icon' => 'customers',
            'group' => 'nav.customers',
            'item' => 'nav.customers_list',
            'statuses' => [
                ['status' => 'new', 'note' => [
                    'en' => 'Just registered, no rental history yet.',
                    'fr' => 'Vient de s\'inscrire, aucun historique de location.',
                    'ar' => 'سُجّل للتو، ولا يملك أي سجل تأجير بعد.',
                ]],
                ['status' => 'regular', 'note' => [
                    'en' => 'Has an established rental history in good standing.',
                    'fr' => 'A un historique de location établi et sans incident.',
                    'ar' => 'له سجل تأجير ثابت وخالٍ من المشاكل.',
                ]],
                ['status' => 'vip', 'note' => [
                    'en' => 'Flagged for preferential treatment: priority service, special rates.',
                    'fr' => 'Marqué pour un traitement préférentiel : service prioritaire, tarifs spéciaux.',
                    'ar' => 'مُعلَّم لمعاملة تفضيلية: خدمة ذات أولوية وأسعار خاصة.',
                ]],
                ['status' => 'watchlist', 'note' => [
                    'en' => 'Flagged for extra scrutiny: past issues or risk factors.',
                    'fr' => 'Marqué pour une attention particulière : incidents passés ou facteurs de risque.',
                    'ar' => 'مُعلَّم لمتابعة إضافية: مشاكل سابقة أو عوامل خطر.',
                ]],
                ['status' => 'archived', 'note' => [
                    'en' => 'No longer an active customer record, kept for history rather than shown in day-to-day lists.',
                    'fr' => 'N\'est plus un dossier client actif, conservé pour l\'historique plutôt qu\'affiché dans les listes courantes.',
                    'ar' => 'لم يعد سجل عميل نشطاً، ويُحتفظ به للسجل التاريخي بدل ظهوره في القوائم اليومية.',
                ]],
            ],
            'terms' => [
                ['label' => 'field.licence_expiry', 'note' => [
                    'en' => 'When the customer\'s driving licence expires, checked against the rental period for eligibility.',
                    'fr' => 'La date d\'expiration du permis de conduire du client, vérifiée par rapport à la période de location pour l\'éligibilité.',
                    'ar' => 'تاريخ انتهاء صلاحية رخصة قيادة العميل، ويُتحقق منه مقابل فترة التأجير للتأكد من الأهلية.',
                ]],
                ['label' => 'field.identity_passport', 'note' => [
                    'en' => 'The ID document number on file, used to verify who\'s actually renting.',
                    'fr' => 'Le numéro de la pièce d\'identité au dossier, utilisé pour vérifier qui loue réellement.',
                    'ar' => 'رقم وثيقة الهوية المسجَّلة في الملف، يُستخدم للتحقق من هوية المستأجر الفعلي.',
                ]],
                ['label' => 'section.additional_driver', 'note' => [
                    'en' => 'A second person authorized to drive the vehicle under the same rental, added with their own licence details.',
                    'fr' => 'Une deuxième personne autorisée à conduire le véhicule dans le cadre de la même location, ajoutée avec ses propres informations de permis.',
                    'ar' => 'شخص إضافي مصرَّح له بقيادة المركبة ضمن التأجير نفسه، ويُضاف مع بيانات رخصته الخاصة.',
                ]],
            ],
        ],
        [
            'id' => 'vehicles',
            'icon' => 'fleet',
            'group' => 'nav.fleet',
            'item' => 'nav.vehicles',
            'statuses' => [
                ['status' => 'available', 'note' => [
                    'en' => 'Free to book right now.',
                    'fr' => 'Libre à la réservation dès maintenant.',
                    'ar' => 'متاحة للحجز الآن.',
                ]],
                ['status' => 'cleaning', 'note' => [
                    'en' => 'Just returned, being prepped before it goes available again.',
                    'fr' => 'Vient d\'être rendu, en préparation avant de redevenir disponible.',
                    'ar' => 'أُعيدت للتو، وهي قيد التحضير قبل أن تصبح متاحة من جديد.',
                ]],
                ['status' => 'reserved', 'note' => [
                    'en' => 'Booked for an upcoming pickup, not out yet.',
                    'fr' => 'Réservé pour une prochaine prise en charge, pas encore sorti.',
                    'ar' => 'محجوزة لاستلام قادم، ولم تخرج بعد.',
                ]],
                ['status' => 'rented', 'note' => [
                    'en' => 'Currently out with a customer.',
                    'fr' => 'Actuellement chez un client.',
                    'ar' => 'حالياً بحوزة أحد العملاء.',
                ]],
                ['status' => 'returned', 'note' => [
                    'en' => 'Just came back from a rental, about to be processed.',
                    'fr' => 'Vient de revenir d\'une location, en cours de traitement.',
                    'ar' => 'عادت للتو من تأجير، وهي قيد المعالجة.',
                ]],
                ['status' => 'maintenance', 'note' => [
                    'en' => 'In the shop, not bookable.',
                    'fr' => 'Chez le garagiste, non réservable.',
                    'ar' => 'في الورشة، وغير قابلة للحجز.',
                ]],
                ['status' => 'damaged', 'note' => [
                    'en' => 'Has an unresolved damage case against it.',
                    'fr' => 'A un dossier de dommage non résolu.',
                    'ar' => 'عليها ملف ضرر لم تتم تسويته بعد.',
                ]],
                ['status' => 'blocked', 'note' => [
                    'en' => 'Manually taken out of rotation for some other reason.',
                    'fr' => 'Retiré manuellement de la rotation pour une autre raison.',
                    'ar' => 'أُخرجت يدوياً من التداول لسبب آخر.',
                ]],
                ['status' => 'retired', 'note' => [
                    'en' => 'Permanently removed from the active fleet.',
                    'fr' => 'Définitivement sorti de la flotte active.',
                    'ar' => 'أُخرجت نهائياً من الأسطول النشط.',
                ]],
                ['status' => 'sold', 'note' => [
                    'en' => 'No longer owned by the agency.',
                    'fr' => 'N\'appartient plus à l\'agence.',
                    'ar' => 'لم تعد مملوكة للوكالة.',
                ]],
            ],
            'terms' => [
                ['label' => 'field.registration', 'note' => [
                    'en' => 'The vehicle\'s licence plate number.',
                    'fr' => 'La plaque d\'immatriculation du véhicule.',
                    'ar' => 'رقم لوحة تسجيل المركبة.',
                ]],
                ['label' => 'field.vin', 'note' => [
                    'en' => 'The manufacturer\'s permanent vehicle identification number, unique to that physical car.',
                    'fr' => 'Le numéro d\'identification du véhicule attribué par le constructeur, unique à ce véhicule physique.',
                    'ar' => 'رقم تعريف المركبة الدائم الصادر عن المصنّع، وهو فريد لتلك المركبة تحديداً.',
                ]],
                ['label' => 'field.category', 'note' => [
                    'en' => 'The pricing/fleet class it belongs to (economy, SUV, luxury...), drives which pricing rules apply.',
                    'fr' => 'La classe tarifaire/flotte à laquelle il appartient (économique, SUV, luxe...), détermine les règles de tarification applicables.',
                    'ar' => 'الفئة التسعيرية/الأسطولية التي تنتمي إليها المركبة (اقتصادية، دفع رباعي، فاخرة...)، وتحدد قواعد التسعير المطبَّقة.',
                ]],
                ['label' => 'field.daily_price', 'note' => [
                    'en' => 'The base rate charged per rental day, before any discounts or add-ons.',
                    'fr' => 'Le tarif de base facturé par jour de location, avant remises ou suppléments.',
                    'ar' => 'السعر الأساسي المفروض لكل يوم تأجير، قبل أي خصومات أو إضافات.',
                ]],
                ['label' => 'field.deposit', 'note' => [
                    'en' => 'The suggested security deposit amount for this specific vehicle.',
                    'fr' => 'Le montant de dépôt de garantie suggéré pour ce véhicule précis.',
                    'ar' => 'مبلغ الضمان المقترح لهذه المركبة تحديداً.',
                ]],
                ['label' => 'field.mileage', 'note' => [
                    'en' => 'The odometer reading, recorded at every inspection so usage between rentals is tracked.',
                    'fr' => 'Le relevé du compteur, enregistré à chaque inspection pour suivre l\'usage entre deux locations.',
                    'ar' => 'قراءة عداد المسافة، تُسجَّل عند كل فحص لتتبع الاستخدام بين عمليتي تأجير.',
                ]],
                ['label' => 'nav.vehicle_documents', 'note' => [
                    'en' => 'Registration, insurance, and inspection paperwork on file for the car, each with its own expiry date the system tracks and flags.',
                    'fr' => 'Les papiers de carte grise, d\'assurance et de contrôle technique au dossier du véhicule, chacun avec sa propre date d\'expiration suivie et signalée par le système.',
                    'ar' => 'أوراق التسجيل والتأمين والفحص التقني الخاصة بالمركبة، ولكل منها تاريخ انتهاء يتابعه النظام وينبّه إليه.',
                ]],
            ],
        ],
        [
            'id' => 'maintenance',
            'icon' => 'fleet',
            'group' => 'nav.fleet',
            'item' => 'nav.maintenance',
            'statuses' => [
                ['status' => 'scheduled', 'note' => [
                    'en' => 'Booked at the shop but hasn\'t started.',
                    'fr' => 'Programmé chez le garagiste mais n\'a pas commencé.',
                    'ar' => 'محجوز عند الورشة ولم يبدأ بعد.',
                ]],
                ['status' => 'in_progress', 'note' => [
                    'en' => 'The car is currently being worked on.',
                    'fr' => 'Le véhicule est actuellement en cours d\'intervention.',
                    'ar' => 'المركبة قيد الإصلاح حالياً.',
                ]],
                ['status' => 'completed', 'note' => [
                    'en' => 'Work finished, car\'s back in rotation.',
                    'fr' => 'Intervention finie, véhicule de retour en circulation.',
                    'ar' => 'انتهى العمل، وعادت المركبة إلى التداول.',
                ]],
            ],
            'terms' => [
                ['label' => 'field.garage', 'note' => [
                    'en' => 'Which workshop or supplier is doing the work.',
                    'fr' => 'Quel atelier ou fournisseur réalise l\'intervention.',
                    'ar' => 'الورشة أو المورّد الذي يقوم بالعمل.',
                ]],
                ['label' => 'field.next_maintenance_date', 'note' => [
                    'en' => 'When (or at what mileage, in the field next to it) this vehicle is next due for service.',
                    'fr' => 'Quand (ou à quel kilométrage, dans le champ voisin) ce véhicule doit passer son prochain entretien.',
                    'ar' => 'الموعد (أو المسافة المقطوعة، في الحقل المجاور) الذي يحين فيه موعد الصيانة القادمة لهذه المركبة.',
                ]],
                ['label' => 'action.save_maintenance', 'note' => [
                    'en' => 'Records a maintenance job against a vehicle, whether scheduled ahead or logged after the fact.',
                    'fr' => 'Enregistre une intervention d\'entretien sur un véhicule, qu\'elle soit planifiée à l\'avance ou consignée après coup.',
                    'ar' => 'يسجّل عملية صيانة لمركبة، سواء كانت مجدولة مسبقاً أو مسجَّلة بعد إنجازها.',
                ]],
            ],
        ],
        [
            'id' => 'incidents',
            'icon' => 'fleet',
            'group' => 'nav.fleet',
            'item' => 'nav.incidents',
            'statuses' => [
                ['status' => 'reported', 'note' => [
                    'en' => 'Logged, not yet looked into.',
                    'fr' => 'Enregistré, pas encore examiné.',
                    'ar' => 'مسجَّل، ولم يُنظر فيه بعد.',
                ]],
                ['status' => 'investigating', 'note' => [
                    'en' => 'Actively being looked into.',
                    'fr' => 'En cours d\'investigation.',
                    'ar' => 'قيد التحقيق حالياً.',
                ]],
                ['status' => 'resolved', 'note' => [
                    'en' => 'Closed out, resolution recorded.',
                    'fr' => 'Clos, résolution enregistrée.',
                    'ar' => 'مُغلق، وتم تسجيل الحل.',
                ]],
                ['status' => 'declined', 'note' => [
                    'en' => 'Claim or responsibility was rejected.',
                    'fr' => 'La réclamation ou la responsabilité a été rejetée.',
                    'ar' => 'رُفضت المطالبة أو المسؤولية.',
                ]],
            ],
            'terms' => [
                ['label' => 'field.damage_type', 'note' => [
                    'en' => 'What kind of incident this is: accident, fine, or other damage.',
                    'fr' => 'Le type d\'incident : accident, amende, ou autre dommage.',
                    'ar' => 'نوع الحادثة: حادث، مخالفة، أو ضرر آخر.',
                ]],
                ['label' => 'field.severity', 'note' => [
                    'en' => 'Minor, moderate, or major, how serious the damage is.',
                    'fr' => 'Mineure, modérée ou majeure : la gravité du dommage.',
                    'ar' => 'طفيفة أو متوسطة أو جسيمة: درجة خطورة الضرر.',
                ]],
                ['label' => 'field.responsible_party', 'note' => [
                    'en' => 'Who\'s on the hook: the customer, a third party, or unknown.',
                    'fr' => 'Qui est responsable : le client, un tiers, ou inconnu.',
                    'ar' => 'الطرف المسؤول: العميل، طرف ثالث، أو غير معروف.',
                ]],
                ['label' => 'field.customer_charge', 'note' => [
                    'en' => 'How much of the estimated cost is being billed to the customer rather than absorbed by the agency.',
                    'fr' => 'La part du coût estimé facturée au client plutôt qu\'absorbée par l\'agence.',
                    'ar' => 'الجزء من التكلفة المقدَّرة الذي يُحمَّل على العميل بدل أن تتحمله الوكالة.',
                ]],
            ],
        ],
        [
            'id' => 'finance',
            'icon' => 'finance',
            'group' => 'nav.finance',
            'item' => 'nav.finance',
            'statuses' => [
                ['status' => 'paid', 'note' => [
                    'en' => 'Payment went through successfully.',
                    'fr' => 'Le paiement a abouti.',
                    'ar' => 'تم الدفع بنجاح.',
                ]],
                ['status' => 'pending', 'note' => [
                    'en' => 'Not yet completed or confirmed.',
                    'fr' => 'Pas encore finalisé ou confirmé.',
                    'ar' => 'لم يكتمل أو يُؤكَّد بعد.',
                ]],
                ['status' => 'failed', 'note' => [
                    'en' => 'The payment attempt didn\'t go through.',
                    'fr' => 'La tentative de paiement n\'a pas abouti.',
                    'ar' => 'لم تنجح محاولة الدفع.',
                ]],
                ['status' => 'held', 'note' => [
                    'en' => 'Deposit: funds are authorized/collected but not yet released or kept.',
                    'fr' => 'Dépôt de garantie : les fonds sont autorisés/encaissés mais pas encore libérés ni conservés.',
                    'ar' => 'الضمان: المبلغ مُصرَّح به/محصَّل لكنه لم يُفرَج عنه أو يُحتجز بعد.',
                ]],
                ['status' => 'received', 'note' => [
                    'en' => 'Deposit: the money has actually landed.',
                    'fr' => 'Dépôt de garantie : les fonds sont effectivement arrivés.',
                    'ar' => 'الضمان: وصل المبلغ فعلياً.',
                ]],
                ['status' => 'partially_retained', 'note' => [
                    'en' => 'Deposit: part of it was kept (for damage, fees) and the rest returned.',
                    'fr' => 'Dépôt de garantie : une partie a été conservée (dommage, frais), le reste a été rendu.',
                    'ar' => 'الضمان: احتُجز جزء منه (بسبب ضرر أو رسوم) وأُعيد الباقي.',
                ]],
                ['status' => 'refunded', 'note' => [
                    'en' => 'Money was given back to the customer.',
                    'fr' => 'Les fonds ont été rendus au client.',
                    'ar' => 'أُعيد المبلغ إلى العميل.',
                ]],
                ['status' => 'void', 'note' => [
                    'en' => 'Cancelled/invalidated, doesn\'t count toward anything.',
                    'fr' => 'Annulé/invalidé, ne compte plus.',
                    'ar' => 'مُلغى/باطل، ولم يعد له أي اعتبار.',
                ]],
                ['status' => 'overdue', 'note' => [
                    'en' => 'Invoice: the payment deadline has passed unpaid.',
                    'fr' => 'Facture : la date limite de paiement est dépassée, impayée.',
                    'ar' => 'الفاتورة: تجاوز موعد السداد النهائي دون دفع.',
                ]],
                ['status' => 'overpaid', 'note' => [
                    'en' => 'More was paid than the invoice actually required.',
                    'fr' => 'Plus a été payé que ce que la facture exigeait réellement.',
                    'ar' => 'دُفع مبلغ أكثر مما تطلبه الفاتورة فعلياً.',
                ]],
                ['status' => 'underpaid', 'note' => [
                    'en' => 'Less was paid than required, balance still open.',
                    'fr' => 'Moins a été payé que requis, solde encore ouvert.',
                    'ar' => 'دُفع مبلغ أقل من المطلوب، والرصيد لا يزال مفتوحاً.',
                ]],
                ['status' => 'waived', 'note' => [
                    'en' => 'The remaining balance was forgiven rather than collected.',
                    'fr' => 'Le solde restant a été abandonné plutôt qu\'encaissé.',
                    'ar' => 'أُسقط الرصيد المتبقي بدل تحصيله.',
                ]],
            ],
            'terms' => [
                ['label' => 'nav.payments', 'note' => [
                    'en' => 'The log of every payment collected from customers, each tied to a specific reservation, with amount, method, and status.',
                    'fr' => 'Le registre de chaque paiement encaissé auprès des clients, rattaché à une réservation précise, avec montant, mode et statut.',
                    'ar' => 'سجل كل دفعة تم تحصيلها من العملاء، مرتبطة بحجز محدد، مع المبلغ وطريقة الدفع والحالة.',
                ]],
                ['label' => 'nav.deposits', 'note' => [
                    'en' => 'The refundable security deposit held against a rental, separate from the rental payment itself.',
                    'fr' => 'Le dépôt de garantie remboursable retenu contre une location, séparé du paiement de la location lui-même.',
                    'ar' => 'مبلغ الضمان القابل للاسترداد المحتجز مقابل التأجير، منفصل عن دفعة التأجير نفسها.',
                ]],
                ['label' => 'nav.invoices', 'note' => [
                    'en' => 'The formal billing document: subtotal, tax, total, and balance due, printable/PDF.',
                    'fr' => 'Le document de facturation officiel : sous-total, taxe, total et solde dû, imprimable/PDF.',
                    'ar' => 'وثيقة الفوترة الرسمية: المجموع الفرعي، الضريبة، الإجمالي، والرصيد المستحق، قابلة للطباعة/PDF.',
                ]],
                ['label' => 'nav.expenses', 'note' => [
                    'en' => 'Money going out: what the agency itself spends per vehicle (maintenance, insurance, fuel...), used to calculate real profitability.',
                    'fr' => 'L\'argent qui sort : ce que l\'agence dépense elle-même par véhicule (entretien, assurance, carburant...), sert à calculer la rentabilité réelle.',
                    'ar' => 'الأموال الصادرة: ما تنفقه الوكالة نفسها على كل مركبة (الصيانة، التأمين، الوقود...)، وتُستخدم لحساب الربحية الفعلية.',
                ]],
                ['label' => 'nav.cash_register', 'note' => [
                    'en' => 'The agency\'s daily physical cash drawer: opened with a starting balance, closed by counting what\'s actually there.',
                    'fr' => 'Le tiroir-caisse physique quotidien de l\'agence : ouvert avec un solde de départ, fermé en comptant ce qu\'il y a réellement.',
                    'ar' => 'صندوق النقد اليومي الفعلي للوكالة: يُفتح برصيد ابتدائي، ويُغلق بعدّ ما هو موجود فعلياً فيه.',
                ]],
                ['label' => 'field.balance', 'note' => [
                    'en' => 'Invoice: total minus whatever\'s already been paid, what\'s left to collect.',
                    'fr' => 'Facture : total moins ce qui a déjà été payé, ce qu\'il reste à encaisser.',
                    'ar' => 'الفاتورة: الإجمالي ناقص ما تم دفعه بالفعل، أي المبلغ المتبقي تحصيله.',
                ]],
                ['label' => 'field.opening_balance', 'note' => [
                    'en' => 'Cash register: the amount in cash at the start of the day, compared to the counted balance at close.',
                    'fr' => 'Caisse : le montant en espèces au début de la journée, comparé au solde compté à la clôture.',
                    'ar' => 'الصندوق: مبلغ النقد عند بداية اليوم، ويُقارن بالرصيد المحسوب عند الإغلاق.',
                ]],
                ['label' => 'field.difference', 'note' => [
                    'en' => 'Counted balance minus expected balance, should be zero; anything else needs an explanation in the closing notes.',
                    'fr' => 'Solde compté moins solde attendu, devrait être nul ; sinon une explication est requise dans les notes de clôture.',
                    'ar' => 'الرصيد المحسوب ناقص الرصيد المتوقَّع، ويجب أن يساوي صفراً؛ وإلا فيلزم تفسير في ملاحظات الإغلاق.',
                ]],
            ],
        ],
        [
            'id' => 'pricing',
            'icon' => 'commercial',
            'group' => 'nav.commercial',
            'item' => 'nav.pricing',
            'statuses' => [],
            'terms' => [
                ['label' => 'field.rule_type', 'note' => [
                    'en' => 'What triggers the adjustment: a season, a weekend, a rental duration, a partner channel, and so on.',
                    'fr' => 'Ce qui déclenche l\'ajustement : une saison, un week-end, une durée de location, un canal partenaire, etc.',
                    'ar' => 'ما الذي يفعّل التعديل: موسم، عطلة نهاية أسبوع، مدة التأجير، قناة شريك، وما إلى ذلك.',
                ]],
                ['label' => 'field.adjustment', 'note' => [
                    'en' => 'Whether the rule adds a fixed amount or a percentage, and the value field next to it is how much.',
                    'fr' => 'Si la règle ajoute un montant fixe ou un pourcentage, et le champ voisin (Valeur) précise le montant.',
                    'ar' => 'ما إذا كانت القاعدة تضيف مبلغاً ثابتاً أو نسبة مئوية، ويوضح الحقل المجاور (القيمة) المقدار.',
                ]],
                ['label' => 'action.create_rule', 'note' => [
                    'en' => 'Adds a new pricing adjustment; rules are computed server-side against a vehicle\'s base daily price, never trusted from the browser.',
                    'fr' => 'Ajoute un nouvel ajustement tarifaire ; les règles sont calculées côté serveur sur le tarif journalier de base d\'un véhicule, jamais confiées au navigateur.',
                    'ar' => 'يضيف تعديلاً تسعيرياً جديداً؛ تُحسب القواعد على الخادم بناءً على السعر اليومي الأساسي للمركبة، ولا يُعتمد على المتصفح لحسابها أبداً.',
                ]],
            ],
        ],
        [
            'id' => 'documents',
            'icon' => 'commercial',
            'group' => 'nav.commercial',
            'item' => 'nav.documents',
            'statuses' => [],
            'terms' => [
                ['label' => 'action.create_quote', 'note' => [
                    'en' => 'Prepares a printable price estimate for a customer before any booking is confirmed, the same document system as contracts and invoices, but for pre-sale paperwork.',
                    'fr' => 'Prépare une estimation de prix imprimable pour un client avant toute confirmation de réservation, le même système documentaire que les contrats et factures, mais pour les documents commerciaux préalables.',
                    'ar' => 'يُعدّ تقديراً سعرياً قابلاً للطباعة لعميل قبل تأكيد أي حجز، ضمن نظام الوثائق نفسه المستخدم للعقود والفواتير، لكنه مخصص للمستندات التجارية التمهيدية.',
                ]],
                ['label' => 'status.quote', 'note' => [
                    'en' => 'A quote hasn\'t become a real reservation yet; converting it creates the actual booking.',
                    'fr' => 'Un devis n\'est pas encore une réservation réelle ; sa conversion crée la réservation effective.',
                    'ar' => 'العرض ليس حجزاً فعلياً بعد؛ وتحويله هو ما ينشئ الحجز الحقيقي.',
                ]],
            ],
        ],
        [
            'id' => 'reports',
            'icon' => 'analytics',
            'group' => 'nav.analytics',
            'item' => 'nav.reports',
            'statuses' => [],
            'terms' => [
                ['label' => 'field.estimated_profit', 'note' => [
                    'en' => 'Revenue from collected payments minus approved vehicle expenses, an estimate that depends on how complete the underlying records are.',
                    'fr' => 'Recettes des paiements encaissés moins les dépenses véhicule approuvées, une estimation qui dépend de l\'exhaustivité des enregistrements.',
                    'ar' => 'إيرادات المدفوعات المحصَّلة ناقص مصاريف المركبات المعتمدة، وهو تقدير يعتمد على مدى اكتمال السجلات الأساسية.',
                ]],
                ['label' => 'field.fleet_utilization', 'note' => [
                    'en' => 'The percentage of available vehicle-days that were actually rented out over the reporting period, versus sitting idle.',
                    'fr' => 'Le pourcentage de jours-véhicule disponibles qui ont été effectivement loués sur la période, par rapport à ceux restés inutilisés.',
                    'ar' => 'نسبة أيام تشغيل المركبات المتاحة التي أُجِّرت فعلياً خلال فترة التقرير، مقارنة بالأيام التي بقيت فيها المركبات دون استخدام.',
                ]],
                ['label' => 'field.average_daily_rate', 'note' => [
                    'en' => 'The average price actually charged per rental day across all bookings in the period, after discounts.',
                    'fr' => 'Le prix moyen réellement facturé par jour de location sur l\'ensemble des réservations de la période, après remises.',
                    'ar' => 'متوسط السعر المفروض فعلياً لكل يوم تأجير عبر جميع الحجوزات خلال الفترة، بعد احتساب الخصومات.',
                ]],
            ],
        ],
        [
            'id' => 'administration',
            'icon' => 'admin',
            'group' => 'nav.administration',
            'item' => 'nav.administration',
            'statuses' => [],
            'terms' => [
                ['label' => 'role.owner', 'note' => [
                    'en' => 'Full access to everything, every agency, every module, the only role that can create new agencies.',
                    'fr' => 'Accès complet à tout, toutes les agences, tous les modules, le seul rôle pouvant créer de nouvelles agences.',
                    'ar' => 'صلاحية كاملة على كل شيء، جميع الوكالات وجميع الوحدات، وهو الدور الوحيد القادر على إنشاء وكالات جديدة.',
                ]],
                ['label' => 'role.agency_manager', 'note' => [
                    'en' => 'Runs one or more assigned agencies day to day: staff, pricing, finance, and fleet, but can\'t create new agencies.',
                    'fr' => 'Dirige au quotidien une ou plusieurs agences attribuées : personnel, tarification, finances et flotte, mais ne peut pas créer de nouvelles agences.',
                    'ar' => 'يدير يومياً وكالة واحدة أو أكثر مُسندة إليه: الموظفين والتسعير والمالية والأسطول، لكن دون صلاحية إنشاء وكالات جديدة.',
                ]],
                ['label' => 'role.rental_agent', 'note' => [
                    'en' => 'Front-desk staff: handles customers, reservations, contracts, and checkout/check-in, plus taking payments.',
                    'fr' => 'Personnel de comptoir : gère les clients, réservations, contrats et prises en charge/retours, ainsi que l\'encaissement.',
                    'ar' => 'موظف الاستقبال: يتعامل مع العملاء والحجوزات والعقود وعمليات التسليم/الاستلام، بالإضافة إلى تحصيل المدفوعات.',
                ]],
                ['label' => 'role.accountant', 'note' => [
                    'en' => 'Owns the money side: payments, deposits, invoices, expenses, and the cash register, plus financial reports.',
                    'fr' => 'Responsable du volet financier : paiements, dépôts de garantie, factures, dépenses et caisse, ainsi que les rapports financiers.',
                    'ar' => 'مسؤول عن الجانب المالي: المدفوعات والضمانات والفواتير والمصاريف والصندوق، بالإضافة إلى التقارير المالية.',
                ]],
                ['label' => 'role.fleet_agent', 'note' => [
                    'en' => 'Owns the vehicles side: fleet status, maintenance, inspections, and damage records.',
                    'fr' => 'Responsable du volet véhicules : statut de la flotte, entretien, inspections et dossiers de dommages.',
                    'ar' => 'مسؤول عن جانب المركبات: حالة الأسطول والصيانة والفحوصات وملفات الأضرار.',
                ]],
                ['label' => 'field.assigned_agencies', 'note' => [
                    'en' => 'Which agencies a staff member can act in; most roles are scoped to one or a handful, only the owner sees every agency by default.',
                    'fr' => 'Les agences dans lesquelles un employé peut agir ; la plupart des rôles sont limités à une ou quelques-unes, seul le propriétaire voit toutes les agences par défaut.',
                    'ar' => 'الوكالات التي يمكن للموظف العمل ضمنها؛ معظم الأدوار مقيَّدة بوكالة واحدة أو بضع وكالات، والمالك وحده يرى جميع الوكالات افتراضياً.',
                ]],
                ['label' => 'nav.agencies', 'note' => [
                    'en' => 'The branch/location records themselves: name, code, currency, and timezone, the unit everything else (vehicles, staff, reservations) is scoped to.',
                    'fr' => 'Les fiches des agences/succursales elles-mêmes : nom, code, devise et fuseau horaire, l\'unité à laquelle tout le reste (véhicules, personnel, réservations) est rattaché.',
                    'ar' => 'سجلات الفروع/المواقع نفسها: الاسم والرمز والعملة والمنطقة الزمنية، وهي الوحدة التي يرتبط بها كل شيء آخر (المركبات، الموظفون، الحجوزات).',
                ]],
            ],
        ],
    ];
}
