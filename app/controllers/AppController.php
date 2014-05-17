<?php
class AppController extends BaseController {

  public function showIndex()
  {
    $vBanList = vBanList::wheresteamUserId(Session::get('user.id'))->orderBy('id','desc')->paginate(20);

    foreach($vBanList as $key => $vBan) {
      $userInfo = $this->grabVBanUser($vBan->vBanUser->community_id);
      $vBanList[$key]->vBanUser = $vBan->vBanUser->community_id;
      if($userInfo) {
        $vBanList[$key]->vBanUser = $userInfo;
      }
    }

    return View::make('user.welcome', array('vBanList' => $vBanList));
  }

  public function doSearch()
  {
    $searchMany = Input::get('doManySearch');

    if(!empty($searchMany))
    {
      $searchMany = explode("\n", $searchMany);
      $vBanList = array();
      $count = 0;
      foreach($searchMany as $oneSearch)
      {
        $searchData = $this->getSteamSearchCommunityId($oneSearch);
        if($searchData['type'] == 'success') {
          $userInfo = $this->grabVBanUser($searchData['data']);
          $vBanList[$key]->vBanUser = $searchData['data'];

          if($userInfo) {
            $vBanList[$count] = new stdClass;
            $vBanList[$count]->vBanUser = $userInfo;
          }
          $count++;
        }
      }

      return View::make('user.welcome', array('vBanList' => $vBanList, 'vBanCount' => $count, 'searching' => true));
    }

    $searchData = $this->getSteamSearchCommunityId(Input::get('doSearch'));

    $this->log->addInfo("doSearch", array(
      "steamUserId" => Session::get('user.id'),
      "displayName" => Session::get('user.name'),
      "ipAddress" => Request::getClientIp(),
      "data" => Input::get('doSearch')
    ));

    if($searchData['type'] == 'success') {
      return Redirect::to("/u/{$searchData['data']}");
    } else {
      return Redirect::to('/')->with('error', $searchData['data']);
    }
  }

  public function showUser($steamCommunityId = '')
  {

    $this->log->addInfo("requestUser", array(
      "steamUserId" => Session::get('user.id'),
      "displayName" => Session::get('user.name'),
      "ipAddress" => Request::getClientIp(),
      "data" => $steamCommunityId
    ));

    if(!is_numeric($steamCommunityId) || !preg_match('/7656119/', $steamCommunityId))
    {
      return Redirect::intended()->withInput()->with('error', 'Invalid ID');
    }

    $userInfo = $this->grabVBanUser($steamCommunityId);

    if(!$userInfo)
    {
      $userInfo = $this->updateVBanUser(null, $steamCommunityId);
      if(!$userInfo) {
        return Redirect::intended()->withInput()->with('error', 'Unable to fetch data');
      }

      $userInfo->get_num_tracking = vBanList::wherevBanUserId($userInfo->id)->count();

      if(Session::get('user.in'))
      {
        $userInfo->is_tracking = isset(vBanList::whereRaw( "steam_user_id = {$sessionUserId} and v_ban_user_id = {$userInfo->id}" )->first()->id)? 1:0;
      }
    }

    $userInfo->steamId = $this->convertSteamId($steamCommunityId);

    return View::make('user.user', array('userInfo' => $userInfo));
  }

  public function addUser()
  {
    $checkURL = explode("/",$_SERVER['HTTP_REFERER']);
    $searchData = Input::get('vBanUserId');

    $this->log->addInfo("addUser", array(
      "steamUserId" => Session::get('user.id'),
      "displayName" => Session::get('user.name'),
      "ipAddress" => Request::getClientIp(),
      "data" => $searchData
    ));

    if(isset(vBanList::whereRaw('steam_user_id = '.Session::get('user.id').' and v_ban_user_id = '.$searchData)->first()->id)) {
      if($checkURL[count($checkURL)-1] == 'search') {
        echo "<script>window.close();</script>";
      } else {
        return Redirect::back()->withInput()->with('error', 'This person is already on list');
      }
    } else {
      $addUserToList = new vBanList;
      $addUserToList->steam_user_id = Session::get('user.id');
      $addUserToList->v_ban_user_id = $searchData;
      $addUserToList->save();
    }

    if($checkURL[count($checkURL)-1] == 'search') {
      echo "<script>window.close();</script>";
    } else {
      return Redirect::back()->with('success', 'User added');
    }
  }


  public function removeUser()
  {
    $searchData = Input::get('vBanUserId');

    $this->log->addInfo("removeUser", array(
      "steamUserId" => Session::get('user.id'),
      "displayName" => Session::get('user.name'),
      "ipAddress" => Request::getClientIp(),
      "data" => $searchData
    ));

    vBanList::whereRaw('steam_user_id = '.Session::get('user.id').' and v_ban_user_id = '.$searchData)->delete();


    $checkURL = explode("/",$_SERVER['HTTP_REFERER']);
    if($checkURL[count($checkURL)-1] == 'search') {
      echo "<script>window.close();</script>";
    } else {
      return Redirect::back()->with('success', 'User removed');
    }
  }

  public function listMostUserTracked()
  {
    $vBanLists = vBanList::all();

    $count = Array();
    $community_id = Array();

    foreach($vBanLists as $vBanList)
    {
      if(isset($count[$vBanList->v_ban_user_id]))
      {
        $count[$vBanList->v_ban_user_id] += 1;
      } else {
        $count[$vBanList->v_ban_user_id] = 1;
        $community_id[$vBanList->v_ban_user_id] = $vBanList->vBanUser->community_id;
      }
    }

    $newCount = $count;
    sort($newCount);

    $arrCount = count($newCount)-1;

    $vBanUsers = Array();
    $arrOfId = Array();
    $vBanUser = Array();
    if($arrCount > -1) {
      for($x = $arrCount; $x > $arrCount-($arrCount - 20 >= 20 ? 20 : $arrCount+1); $x--)
      {
        $keyOfId = array_search($newCount[$x], $count);
        $vBanUser = $this->grabVBanUser($community_id[$keyOfId]);
        if($vBanUser) {
          $vBanUsers[] = $vBanUser;
        } else {
          $vBanUsers[] = $community_id[$keyOfId];
        }
        unset($newCount[$x]);
        unset($count[$keyOfId]);
      }
    }
    return View::make('user.userList', array('hatedUsers' => true, 'vBanUsers' => $vBanUsers));
  }

  public function showLatestUserAdded()
  {
    $vBanLists = vBanList::all()->sortByDesc('id')->take(20);
    $vBanUsers = Array();

    foreach($vBanLists as $vBanList) {
      $userInfo = $this->grabVBanUser($vBanList->vBanUser->community_id);
      if($userInfo) {
        $vBanUsers[] = $userInfo;
      } else {
        $vBanUsers[] = $vBanList->vBanUser->community_id;
      }
    }

    return View::make('user.userList', array('latestUserAdded' => true, 'vBanUsers' => $vBanUsers));
  }

  private function getSteamSearchCommunityId($data)
  {
    $data = strtolower(trim($data));
    if (!empty($data))
    {
      if (strlen($data) > 100) return array('type' => 'error', 'data' => 'Field too long');

      if (substr($data, 0, 6) == 'steam_')
      {
        $tmp = explode(':',$data);
        if ((count($tmp) == 3) && is_numeric($tmp[1]) && is_numeric($tmp[2]))
        {
          $communityid = bcadd(($tmp[2] * 2) + $tmp[1], '76561197960265728');
          return array('type' => 'success','data' => $communityid);
        }
        else
        {
          return array('type' => 'error', 'data' => 'Invalid Steam ID');
        }

      }
      else if ($p = strrpos($data, '/'))
      {
        $tmp = explode('/',$data);
        $a = null;
        foreach ($tmp as $key => $item)
        {
          if (is_numeric($item))
          {
            $a = $item;
            break;
          } else if ($item == 'id') {
            $data = $tmp[$key+1];
          }
        }
        if (is_numeric($a) && preg_match('/7656119/', $a))
        {
          return array('type' => 'success', 'data' => $a);
        }
        else
        {
          $userInfo = $this->cURLPage("http://steamcommunity.com/id/{$data}/?xml=1&".time(), false);

          try {
            $userInfo = simplexml_load_string($userInfo);
          } catch(Exception $ex) {
            return array('type' => 'error', 'data' => 'Steam API error');
          }

          if(!is_object($userInfo)) return array('type' => 'error', 'data' => 'Invalid input');

          $steamid64 = (string) $userInfo->steamID64;
          if (!preg_match('/7656119/', $steamid64)) return array('type' => 'error', 'data' => 'Invalid link');
          else return array('type' => 'success', 'data' => $steamid64);
        }
      }
      else if (is_numeric($data) && preg_match('/7656119/', $data))
      {
        return array('type' => 'success', 'data' => $data);
      }
      else
      {
        $userInfo = $this->cURLPage("http://steamcommunity.com/id/{$data}/?xml=1&".time(), false);

        try {
          $userInfo = simplexml_load_string($userInfo);
        } catch(Exception $ex) {
          return array('type' => 'error', 'data' => 'Steam API error');
        }

        if(!is_object($userInfo)) return array('type' => 'error', 'data' => 'Invalid input');

        $steamid64 = (string) $userInfo->steamID64;
        if (!preg_match('/7656119/', $steamid64)) return array('type' => 'error', 'data' => 'Invalid input');
        else return array('type' => 'success', 'data' => $steamid64);
      }
    }

    return array('type' => 'error', 'data' => 'Invalid or empty input');
  }
}
