<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:200',
            'num_questions' => 'required|integer|min:1|max:20',
            'difficulty' => 'required|in:Easy,Medium,Hard',
        ]);

        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return response()->json([
                'message' => 'Gemini API key is missing from .env',
            ], 500);
        }

        $topic = $validated['topic'];
        $count = (int) $validated['num_questions'];
        $difficulty = $validated['difficulty'];

        $prompt = "Create exactly {$count} multiple-choice questions about {$topic} at {$difficulty} difficulty.

Return only valid JSON in this format:
{
  \"questions\": [
    {
      \"question\": \"Question text\",
      \"options\": {
        \"A\": \"Option A\",
        \"B\": \"Option B\",
        \"C\": \"Option C\",
        \"D\": \"Option D\"
      },
      \"correct_answer\": \"A\",
      \"explanation\": \"Short explanation\"
    }
  ]
}

Generate exactly {$count} questions. Each question must have four options and one correct answer. Do not include Markdown.";


try {
    $response = Http::timeout(90)->post(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key=' . $apiKey,
        [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ]
    );


            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return response()->json([
                    'message' => 'Gemini API failed. Check the API key, model availability, and quota.',
                ], 502);
            }

            $text = $response->json(
                'candidates.0.content.parts.0.text'
            );

            $quiz = is_string($text)
                ? json_decode($text, true)
                : null;

            if (
                !is_array($quiz) ||
                !isset($quiz['questions']) ||
                !is_array($quiz['questions']) ||
                count($quiz['questions']) !== $count
            ) {
                return response()->json([
                    'message' => 'Invalid quiz returned by AI. Please try again.',
                ], 502);
            }

            foreach ($quiz['questions'] as $question) {
                if (
                    !is_array($question) ||
                    !isset(
                        $question['question'],
                        $question['options'],
                        $question['correct_answer']
                    ) ||
                    !is_array($question['options']) ||
                    !isset(
                        $question['options']['A'],
                        $question['options']['B'],
                        $question['options']['C'],
                        $question['options']['D']
                    ) ||
                    !in_array(
                        $question['correct_answer'],
                        ['A', 'B', 'C', 'D'],
                        true
                    )
                ) {
                    return response()->json([
                        'message' => 'Invalid question format returned by AI.',
                    ], 502);
                }
            }

            return response()->json([
                'topic' => $topic,
                'difficulty' => $difficulty,
                'questions' => $quiz['questions'],
            ]);

        } catch (\Throwable $e) {
            Log::error('Quiz generation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to generate quiz. Please try again.',
            ], 500);
        }
    }
}
