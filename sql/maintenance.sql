
-- Patients and hospital stays

-- reservations, visits and stays

-- active stays have valid visits
select * from stays s join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
where s.'Status' != v.'Status';

-- Patients & Guests, PSG


-- Money