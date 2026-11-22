-- Sync the current hiring role content to another database by role slug.
-- Re-runnable, and preserves is_open and application_limit on the destination.

UPDATE hiring_openings
SET
    title = 'Optometrist (Resident or Locum)',
    description = 'Join Bealet Optical Center as an Optometrist and help people achieve better vision and healthier eyes. Conduct professional eye examinations, assess visual needs, recommend corrective solutions and support quality eye-care delivery. Resident and locum applicants are welcome.',
    requirements = 'Recognised Optometry qualification; appropriate Ghanaian professional registration; competence in eye examinations and vision assessments; prescription interpretation; patient communication; clinical documentation; professional ethics and confidentiality.',
    candidate_preference = 'A patient-focused, ethical and confident eye-care professional with strong clinical judgement and excellent communication skills. Resident and locum applicants are welcome.'
WHERE slug = 'optometrist-resident-locum';

UPDATE hiring_openings
SET
    title = 'Female Receptionist',
    description = 'Be the welcoming first point of contact for patients and visitors. Handle patient registration, appointment coordination, billing support, customer enquiries and accurate record management while supporting a smooth customer experience.',
    requirements = 'Good communication skills; customer service abilities; basic computer literacy; numerical and record-keeping skills; attention to detail; confidentiality; integrity and professional conduct.',
    branch = 'Madina',
    schedule = '3 Days a Week',
    candidate_preference = 'An organised, courteous and dependable individual who enjoys interacting with people and can work calmly in a busy customer-facing environment.'
WHERE slug = 'receptionist';

UPDATE hiring_openings
SET
    title = 'Showroom Attendant',
    description = 'Help customers discover eyewear that suits their vision needs, personal style and preferences. Welcome customers, present frames, assist with product selection, maintain showroom presentation and support sales activities.',
    requirements = 'Good communication and interpersonal skills; customer service or retail experience is an advantage; interest in eyewear; product presentation skills; organisation; basic computer literacy and integrity.',
    candidate_preference = 'A friendly, persuasive and service-oriented individual with an interest in eyewear and retail who enjoys helping customers make informed choices.'
WHERE slug = 'showroom-attendant';

UPDATE hiring_openings
SET
    title = 'Technician / Optician',
    description = 'Support optical service delivery through accurate eyewear preparation, fitting, adjustment and technical support. Help ensure spectacles meet prescription and quality standards while delivering comfortable, properly fitted eyewear.',
    requirements = 'Relevant optical training or practical experience; understanding of spectacle lenses and frames; optical measurements; frame adjustment and repair skills; precision; attention to detail; equipment care and teamwork.',
    candidate_preference = 'A technically minded, patient and detail-oriented individual who takes pride in accurate workmanship and quality eyewear.'
WHERE slug = 'technician-optician';

UPDATE hiring_openings
SET
    title = 'Account Manager',
    description = 'Build and maintain strong professional relationships that support Bealet Optical Center growth. Engage existing and prospective clients, identify business opportunities, coordinate client needs and develop lasting business relationships.',
    requirements = 'Relevant qualification or experience in sales, marketing or business administration; communication and negotiation skills; relationship management; business development; presentation skills; organisation and reporting abilities.',
    candidate_preference = 'A confident, commercially minded and relationship-focused individual who can build trust, communicate value and identify business opportunities.'
WHERE slug = 'account-manager';

UPDATE hiring_openings
SET
    title = 'Delivery Officer',
    description = 'Ensure Bealet Optical Center products and documents reach the right destinations safely and on time. Handle authorised deliveries, maintain delivery records and provide professional customer interaction.',
    requirements = 'Good communication skills; ability to follow delivery instructions; route knowledge or willingness to learn; punctuality; careful handling of products; smartphone literacy; valid driving or riding licence where required by the approved delivery method.',
    candidate_preference = 'A reliable, organised and punctual individual who takes responsibility for deliveries, communicates professionally and handles company property with care.'
WHERE slug = 'delivery-officer';
