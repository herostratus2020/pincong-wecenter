<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/

if (!defined('IN_ANWSION'))
{
	die;
}

class register_class extends AWS_MODEL
{

	public function is_captcha_required()
	{
		if (S::get('register_seccode') == 'Y')
		{
			return true;
		}

		return false;
	}

	public function register($username, $scrambled_password, $client_salt)
	{
		if ($uid = $this->insert_user($username, $scrambled_password, $client_salt))
		{
			$this->model('account')->update_notification_setting_fields(S::get('new_user_notification_setting'), $uid);
			//$this->model('search_fulltext')->push_index('user', $username, $uid);

			if ($def_focus_uids_str = S::get('def_focus_uids'))
			{
				$def_focus_uids = explode(',', $def_focus_uids_str);

				foreach ($def_focus_uids as $key => $val)
				{
					$this->model('follow')->user_follow_add($uid, $val);
				}
			}

			$this->model('currency')->process($uid, 'REGISTER', S::get('currency_system_config_register'), '初始资本');
		}

		return $uid;
	}

	/**
	 * 插入用户数据
	 *
	 * @param string
	 * @param string
	 * @param string
	 * @param int
	 * @param string
	 * @return int
	 */
	public function insert_user($username, $scrambled_password, $client_salt)
	{
		if (!$username OR !$scrambled_password)
		{
			return false;
		}

		if ($this->model('account')->username_exists($username))
		{
			return false;
		}

		$flagged = 0;
		if (S::get('new_user_watching') == 'Y')
		{
			$flagged = -1;
		}

		$uid = $this->insert('users', array(
			'user_name' => htmlspecialchars($username),
			'password' => $this->model('password')->hash($scrambled_password),
			'salt' => $client_salt,
			'password_version' => 2,
			'group_id' => 0,
			'flagged' => $flagged,
			'sex' => 0,
			'avatar_file' => null,
			'reg_time' => fake_time()
		));

		return $uid;
	}


    public function check_username_char($user_name)
    {
        if (is_digits($user_name))
        {
            return AWS_APP::lang()->_t('用户名不能为纯数字');
        }

        if (strstr($user_name, '-') OR strstr($user_name, '.') OR strstr($user_name, '/') OR strstr($user_name, '%') OR strstr($user_name, '__'))
        {
            return AWS_APP::lang()->_t('用户名不能包含 - / . % 与连续的下划线');
        }

        $char = substr($user_name, 0, 1);
        if (strstr('0123456789_', $char))
        {
            return AWS_APP::lang()->_t('用户名不能以数字或下划线开头');
        }

        $length_min = S::get_int('username_length_min');
        $length_max = S::get_int('username_length_max');
        $length = cjk_strlen($user_name);
        if ($length < $length_min OR $length > $length_max)
        {
            return AWS_APP::lang()->_t('用户名字数不符合规则');
        }

        $bytes_min = S::get_int('username_bytes_min');
        $bytes_max = S::get_int('username_bytes_max');
        $bytes = strlen($user_name);
        if ( ($bytes_min AND $bytes < $bytes_min) OR
            ($bytes_max AND $bytes > $bytes_max) )
        {
            return AWS_APP::lang()->_t('用户名长度不符合规则');
        }

        switch(S::get('username_rule'))
        {
            case 1:
                if (!preg_match('/^[\x{4e00}-\x{9fa5}_a-zA-Z0-9]+$/u', $user_name))
                {
                    return AWS_APP::lang()->_t('用户名只允许出现汉字、字母、数字或下划线');
                }
                break;

            case 2:
                if (!preg_match("/^[a-zA-Z0-9_]+$/i", $user_name))
                {
                    return AWS_APP::lang()->_t('用户名只允许出现字母、数字或下划线');
                }
                break;

            case 3:
                if (!preg_match("/^[\x{4e00}-\x{9fa5}]+$/u", $user_name))
                {
                    return AWS_APP::lang()->_t('用户名只允许出现汉字');
                }
                break;

            default:
                break;
        }

        return false;
    }

    /**
     * 检查用户名中是否包含敏感词或用户信息保留字
     *
     * @param string
     * @return boolean
     */
    public function check_username_sensitive_words($user_name)
    {
        return S::content_contains('censoruser', $user_name, true);
    }

}