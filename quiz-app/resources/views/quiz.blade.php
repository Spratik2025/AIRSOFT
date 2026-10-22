
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Quiz Generator</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fc;
            color: #172033;
        }

        header {
            background: #4338ca;
            color: white;
            padding: 22px 8%;
        }

        header h1 { margin: 0; }
        header p { margin-bottom: 0; }

        main {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 18px;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 8px 30px #17203312;
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-weight: bold;
            margin: 18px 0 8px;
        }

        input[type="text"], select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            background: #4338ca;
            color: white;
            border: 0;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover { background: #3730a3; }
        button:disabled { opacity: 0.6; cursor: wait; }

        .note {
            color: #64748b;
            font-size: 14px;
            margin-top: 16px;
        }

        .hidden { display: none; }

        .question {
            border-bottom: 1px solid #e2e8f0;
            padding: 18px 0;
        }

        .question h3 { margin-top: 0; }

        .option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: normal;
        }

        .option input { margin-top: 3px; }
        .option.correct { background: #dcfce7; border-color: #16a34a; }
        .option.wrong { background: #fee2e2; border-color: #dc2626; }

        .feedback { margin-top: 12px; font-size: 14px; }
        .error { color: #b91c1c; }
        .success { color: #166534; }

        #score {
            font-size: 24px;
            font-weight: bold;
            color: #4338ca;
        }

        @media (max-width: 600px) {
            header { padding: 20px; }
            main { margin-top: 24px; }
            .card { padding: 20px; }
        }
    </style>
</head>

<body>
<header>
    <h1>AI Quiz Generator</h1>
    <p>Generate a quiz and test your knowledge.</p>
</header>

<main>
    <section class="card">
        <h2>Create Your Quiz</h2>
        <p>Choose a topic and customize your quiz.</p>

        <form id="quizForm">
            <label for="topic">Quiz Topic</label>
            <input
                id="topic"
                name="topic"
                type="text"
                placeholder="e.g. Python, Machine Learning"
                maxlength="200"
                required
            >

            <label for="count">Number of Questions</label>
            <select id="count" name="count">
                <option value="5">5 Questions</option>
                <option value="10">10 Questions</option>
                <option value="15">15 Questions</option>
                <option value="20">20 Questions</option>
            </select>

            <label for="difficulty">Difficulty Level</label>
            <select id="difficulty" name="difficulty">
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>
            </select>

            <button type="submit" id="generateBtn">Generate Quiz</button>
        </form>

        <p class="note" id="status" role="status">
            Enter a topic to generate your quiz using Gemini AI.
        </p>
    </section>

    <section id="quizSection" class="card hidden">
        <h2 id="quizTitle">Your Quiz</h2>
        <form id="answerForm">
            <div id="questions"></div>
            <button type="submit" id="submitBtn">Submit Answers</button>
        </form>
    </section>

    <section id="resultSection" class="card hidden" aria-live="polite">
        <h2>Quiz Results</h2>
        <p id="score"></p>
        <p id="resultMessage"></p>
        <button type="button" id="retryBtn">Create Another Quiz</button>
    </section>
</main>

<script>
    const quizForm = document.getElementById('quizForm');
    const answerForm = document.getElementById('answerForm');
    const generateBtn = document.getElementById('generateBtn');
    const submitBtn = document.getElementById('submitBtn');
    const statusBox = document.getElementById('status');
    const questionsBox = document.getElementById('questions');
    const quizSection = document.getElementById('quizSection');
    const resultSection = document.getElementById('resultSection');

    let currentQuestions = [];

    function showStatus(message, isError = false) {
        statusBox.textContent = message;
        statusBox.className = isError ? 'note error' : 'note';
    }

    quizForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const topic = document.getElementById('topic').value.trim();
        const count = Number(document.getElementById('count').value);
        const difficulty = document.getElementById('difficulty').value;

        if (!topic) {
            showStatus('Please enter a quiz topic.', true);
            return;
        }

        generateBtn.disabled = true;
        generateBtn.textContent = 'Generating Quiz...';
        showStatus('Gemini AI is generating your questions. Please wait.');
        quizSection.classList.add('hidden');
        resultSection.classList.add('hidden');

        try {
            const response = await fetch('/generate-quiz', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content
                },
                body: JSON.stringify({
                    topic: topic,
                    num_questions: count,
                    difficulty: difficulty
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Quiz generation failed.');
            }

            if (!Array.isArray(data.questions) ||
                data.questions.length !== count) {
                throw new Error('The AI returned an unexpected number of questions.');
            }

            currentQuestions = data.questions;
            renderQuestions(data.topic || topic, data.difficulty || difficulty);
            showStatus('Quiz generated successfully!');
            quizSection.classList.remove('hidden');
            quizSection.scrollIntoView({ behavior: 'smooth' });

        } catch (error) {
            showStatus(error.message || 'Something went wrong. Please try again.', true);
        } finally {
            generateBtn.disabled = false;
            generateBtn.textContent = 'Generate Quiz';
        }
    });

    function renderQuestions(topic, difficulty) {
        questionsBox.replaceChildren();
        answerForm.reset();
        submitBtn.disabled = false;

        document.getElementById('quizTitle').textContent =
            topic + ' — ' + difficulty + ' Quiz';

        currentQuestions.forEach((question, index) => {
            const card = document.createElement('div');
            card.className = 'question';

            const heading = document.createElement('h3');
            heading.textContent = (index + 1) + '. ' + question.question;
            card.appendChild(heading);

            ['A', 'B', 'C', 'D'].forEach(letter => {
                const label = document.createElement('label');
                label.className = 'option';

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'question_' + index;
                radio.value = letter;
                radio.required = true;

                const text = document.createElement('span');
                text.textContent = letter + '. ' + question.options[letter];

                label.appendChild(radio);
                label.appendChild(text);
                card.appendChild(label);
            });

            const feedback = document.createElement('p');
            feedback.className = 'feedback';
            feedback.hidden = true;
            card.appendChild(feedback);

            questionsBox.appendChild(card);
        });
    }

    answerForm.addEventListener('submit', function (event) {
        event.preventDefault();

        let score = 0;

        currentQuestions.forEach((question, index) => {
            const selected = answerForm.querySelector(
                'input[name="question_' + index + '"]:checked'
            );

            const selectedAnswer = selected ? selected.value : null;
            const correctAnswer = question.correct_answer;
            const card = questionsBox.children[index];
            const options = card.querySelectorAll('.option');
            const feedback = card.querySelector('.feedback');

            options.forEach(label => {
                const radio = label.querySelector('input');
                radio.disabled = true;

                if (radio.value === correctAnswer) {
                    label.classList.add('correct');
                }

                if (radio.checked && radio.value !== correctAnswer) {
                    label.classList.add('wrong');
                }
            });

            if (selectedAnswer === correctAnswer) {
                score++;
                feedback.textContent = 'Correct answer!';
                feedback.className = 'feedback success';
            } else {
                feedback.textContent =
                    'Correct answer: ' + correctAnswer + '. ' +
                    (question.options[correctAnswer] || '');

                feedback.className = 'feedback error';
            }

            if (question.explanation) {
                feedback.textContent += ' Explanation: ' + question.explanation;
            }

            feedback.hidden = false;
        });

        document.getElementById('score').textContent =
            'Your Score: ' + score + ' / ' + currentQuestions.length;

        document.getElementById('resultMessage').textContent =
            score === currentQuestions.length
                ? 'Excellent! You answered every question correctly.'
                : 'Keep practising to improve your score!';

        submitBtn.disabled = true;
        resultSection.classList.remove('hidden');
        resultSection.scrollIntoView({ behavior: 'smooth' });
    });

    document.getElementById('retryBtn').addEventListener('click', function () {
        resultSection.classList.add('hidden');
        quizSection.classList.add('hidden');
        quizForm.reset();
        showStatus('Enter a topic to generate another quiz.');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
</script>
</body>
</html>
