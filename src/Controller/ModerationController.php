<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\Auth; use App\Core\Request; use App\Core\Response; use App\Core\Session; use App\Service\EmailService; use App\Store\FlagsStore; use App\Store\RecipeStore; use App\Store\UserStore; use App\Util\Csrf;
final class ModerationController
{
    private FlagsStore $flagsStore;
    public function __construct(private RecipeStore $recipeStore, private UserStore $userStore, private Session $session, private Auth $auth, private EmailService $emailService) { $this->flagsStore = new FlagsStore(); }
    public function queue(Request $request): Response
    {
        $flags = $this->flagsStore->activeFlags();
        foreach ($flags as $index => $flag) {
            $flags[$index]['recipe']   = $this->recipeStore->findById((string) $flag['recipe_id']);
            $flags[$index]['reporter'] = $this->userStore->findById((string) $flag['user_id']);
        }
        return new Response(render('moderation/queue', ['auth' => $this->auth, 'flags' => $flags], 'layout/admin'));
    }
    public function resolve(Request $request): Response
    {
        if (!Csrf::validateToken((string) $request->post('csrf_token', ''))) { flash('error', 'Your session expired.'); return Response::redirect('/moderation'); }
        $flagId = (string) $request->post('flag_id', '');
        $action = (string) $request->post('action', 'approve');
        $this->flagsStore->resolve($flagId, $action);
        // Find the recipe linked to this flag so we can take the appropriate action.
        // We fetch it from the queue response; re-query moderation_flags for the recipe_id.
        $stmt = _db()->prepare('SELECT recipe_id FROM moderation_flags WHERE id = ?');
        $stmt->execute([$flagId]);
        $row = $stmt->fetch();
        if ($row) {
            $recipeId = (string) $row['recipe_id'];
            if ($action === 'approve') $this->recipeStore->update($recipeId, ['status' => 'published']);
            elseif ($action === 'block') $this->recipeStore->update($recipeId, ['status' => 'blocked']);
            elseif ($action === 'delete') $this->recipeStore->softDelete($recipeId);
            $recipe = $this->recipeStore->findById($recipeId);
            if ($recipe) { $owner = $this->userStore->findById((string) $recipe['author_id']); if ($owner) $this->emailService->sendModerationNotice($owner['email'], 'Moderation update for your recipe', 'A moderator reviewed your recipe and marked it as: ' . $action); }
        }
        append_audit('moderation.resolve', ['flag_id' => $flagId, 'action' => $action]);
        flash('success', 'Flag resolved.');
        return Response::redirect('/moderation');
    }
}
