<?php namespace Zeropingheroes\Lanager\Models\Playlist\Item;

use Zeropingheroes\Lanager\Models\BaseModel;
use Zeropingheroes\Lanager\Models\Playlist;
use Illuminate\Support\MessageBag;
use Auth, DB;

class Vote extends BaseModel {

	protected $table = 'playlist_item_votes';

	public static $rules = array(
		'vote'	=> 'required|in:-1,1',
	);
	
	public function item()
	{
		return $this->belongsTo('Zeropingheroes\Lanager\Models\Playlist\Item');
	}

	public function user()
	{
		return $this->belongsTo('Zeropingheroes\Lanager\Models\User');
	}

	public function beforeSave()
	{
		$errors = new MessageBag;

		$alreadyVoted = Vote::where('user_id', Auth::user()->id)
			->where('playlist_item_id', $this->playlist_item_id)
			->count();

		if( $alreadyVoted )
		{
			$this->validationErrors = $errors->add('error', 'You have already cast a vote on this item.' );
			return false;
		}

		if( $this->playlist_item_id != Playlist\Item::unplayed()->first()->id )
		{
			$this->validationErrors = $errors->add('error', 'This item is not currently playing.' );
			return false;
		}
		
		$item = Playlist\Item::find($this->playlist_item_id);

		// Skip the item if we have met or exceeded the downvote threshold
		$activeSessions = DB::table('sessions')->where('last_activity', '>', time()-600)->count();
		$votesRequired = $item->playlist->user_skip_threshold * $activeSessions;

		if( (abs($item->votes()->sum('vote'))+1) >= $votesRequired ) // if this vote tips the item beyond the threshold
		{
			// skip the item
			$item->playback_state = 2;
			$item->skip_reason = 'Downvoted by users';
			$item->save();
		}
	}
}