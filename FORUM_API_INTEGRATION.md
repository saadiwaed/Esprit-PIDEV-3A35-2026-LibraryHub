# Forum API Integration - Toxicity Moderation

## API used
- **Google Perspective API** (`comments:analyze`)
- Goal: detect toxic/inappropriate language in forum **Posts** and **Comments** before publication.

## Configuration
Environment variables in `.env`:

```dotenv
PERSPECTIVE_API_ENDPOINT="https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze"
PERSPECTIVE_API_KEY=""
FORUM_MODERATION_API_TIMEOUT=3
FORUM_MODERATION_TOXICITY_THRESHOLD=0.60
```

Add a valid `PERSPECTIVE_API_KEY` to activate external moderation.

## Service architecture
- `src/Service/Forum/PerspectiveApiClient.php`
  - Handles HTTP communication with Perspective API.
  - Applies timeout.
  - Parses toxicity score.
  - Logs API/network failures.
  - Gracefully returns `null` when unavailable.
- `src/Service/Forum/ForumContentModerationService.php`
  - Business moderation logic (threshold, block/allow decision).
  - Converts API score to a forum moderation decision object.
- `src/Service/Forum/ForumContentModerationResult.php`
  - Typed result for controller usage (`blocked`, `apiAvailable`, `toxicityScore`, `message`).

## Real forum integration points
- `src/Controller/PostController.php`
  - `new()` (back office post creation)
  - `edit()` (back office post update)
  - `frontNew()` (front office post creation)
  - `addComment()` (front office comment creation)

## Behavior
- If toxicity score >= threshold:
  - Post/comment is blocked.
  - User gets a clear validation/flash message.
- If API is down, times out, or key is missing:
  - Forum feature still works.
  - A local fallback rule blocks only obvious toxic expressions.
  - Failure is logged for operators.

## UI visibility
- Front-office publishing page includes API moderation notice.
- Back-office post form includes API moderation notice.
- Success messages include toxicity score when API is available.
