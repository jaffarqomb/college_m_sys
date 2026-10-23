<?php

/*
|--------------------------------------------------------------------------
| Chatbot Knowledge Base
|--------------------------------------------------------------------------
|
| Intents drive the rule-based chatbot on the public website. Each intent
| maps visitor question patterns to an answer. Patterns are matched in
| order; the first hit wins. If no intent matches, the fallback reply is
| used.
|
*/

return [

    'cooldown' => 30, // seconds between visitor messages

    'messages' => [
        'fallback' => "I didn't quite catch that. I can help with: programs & admissions, tuition & fees, timetables, contact details, or visiting campus. You can also reach our team through the contact page.",
        'rate_limited' => 'You are sending messages a little too quickly. Please wait a moment and try again.',
        'greeting' => 'Hi there! 👋 I am the Automated College System assistant. Ask me about programs, admissions, fees, or our AI timetable feature.',
    ],

    'intents' => [
        'greeting' => [
            'patterns' => ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'salam'],
            'answer' => 'Hello! Welcome to Automated College System. How can I help you today?',
        ],
        'programs' => [
            'patterns' => ['program', 'course', 'major', 'degree', 'faculty', 'department', 'study', 'studying'],
            'answer' => 'We offer a wide range of programs across our departments — from Computer Science and Data Science to Business Analytics and Engineering. Contact the registrar for the full catalog or explore programs on the About page.',
        ],
        'admissions' => [
            'patterns' => ['admission', 'apply', 'application', 'enroll', 'enrollment', 'register', 'registration', 'join'],
            'answer' => 'Admissions are open! Create an account, complete your profile, and our admissions team will guide you through enrollment. Registration deadlines are set per term by the registrar.',
        ],
        'fees' => [
            'patterns' => ['fee', 'fees', 'tuition', 'cost', 'payment', 'price', 'pay', 'invoice', 'scholarship'],
            'answer' => 'Tuition varies by program and is billed per term through your portal — you can view invoices and pay online. Scholarships and payment plans are available; the finance office can tell you more.',
        ],
        'timetable' => [
            'patterns' => ['timetable', 'schedule', 'ai timetable', 'class time', 'classes', 'conflict', 'scheduling'],
            'answer' => 'Our AI Timetable engine automatically builds conflict-free class schedules, balancing rooms, instructors, and student groups. Once published, your personal schedule appears right in your portal.',
        ],
        'grades' => [
            'patterns' => ['grade', 'grades', 'gpa', 'transcript', 'exam', 'results', 'score', 'scores'],
            'answer' => 'Grades are posted by your instructors as assessments are marked. You can track every score, weighted final grade, and GPA in your portal gradebook.',
        ],
        'contact' => [
            'patterns' => ['contact', 'email', 'phone', 'call', 'staff', 'office', 'location', 'address', 'visit'],
            'answer' => 'You can reach us at '.(config('site.contact.email')).' or '.(config('site.contact.phone')).'. We are located at '.(config('site.contact.address')).'. The contact page has a form too!',
        ],
    ],
];
