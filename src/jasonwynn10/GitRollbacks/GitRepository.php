<?php
declare(strict_types=1);
	/**
	 * @author  Jan Pecha, <janpecha@email.cz>
	 * @license New BSD License (BSD-3)
	 */

	/*
Copyright © 2013 Jan Pecha (https://www.janpecha.cz/) All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:
* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright notice, this
  list of conditions and the following disclaimer in the documentation and/or
  other materials provided with the distribution.
* Neither the name of Jan Pecha nor the names of its contributors may be used to
  endorse or promote products derived from this software without specific prior
  written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS “AS IS” AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
	 */

	namespace jasonwynn10\GitRollbacks;

	class GitRepository
	{
		/** @var string $repository */
		protected $repository;

		/** @var string|null $cwd @internal */
		protected $cwd;

		/**
		 * GitRepository constructor.
		 *
		 * @param string $repository
		 *
		 * @throws GitException
		 */
		public function __construct(string $repository)
		{
			if(basename($repository) === '.git')
			{
				$repository = dirname($repository);
			}

			$this->repository = realpath($repository);

			if($this->repository === false)
			{
				throw new GitException("Repository '$repository' not found.");
			}
		}

		/**
		 * @return string
		 */
		public function getRepositoryPath() : string
		{
			return $this->repository;
		}

		/**
		 * Creates a tag.
		 * `git tag <name>`
		 *
		 * @param string $name
		 * @param string[]|null $options
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function createTag(string $name, ?array $options = null) : self
		{
			return $this->begin()
				->run('git tag', $options, $name)
				->end();
		}

		/**
		 * Removes tag.
		 * `git tag -d <name>`
		 *
		 * @param string $name
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function removeTag(string $name) : self
		{
			return $this->begin()
				->run('git tag', array(
					'-d' => $name,
				))
				->end();
		}

		/**
		 * Renames tag.
		 * `git tag <new> <old>`
		 * `git tag -d <old>`
		 *
		 * @param string $oldName
		 * @param string $newName
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function renameTag(string $oldName, string $newName) : self
		{
			return $this->begin()
				// http://stackoverflow.com/a/1873932
				// create new as alias to old (`git tag NEW OLD`)
				->run('git tag', $newName, $oldName)
				// delete old (`git tag -d OLD`)
				->removeTag($oldName) // WARN! removeTag() calls end() method!!!
				->end();
		}

		/**
		 * Returns list of tags in repo.
		 *
		 * @return string[]|null
		 * @throws GitException
		 */
		public function getTags() : ?array
		{
			return $this->extractFromCommand('git tag', 'trim');
		}

		/**
		 * Merges branches.
		 * `git merge <options> <name>`
		 *
		 * @param string $branch
		 * @param string[]|null $options
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function merge(string $branch, ?array $options = null) : self
		{
			return $this->begin()
				->run('git merge', $options, $branch)
				->end();
		}

		/**
		 * Creates new branch.
		 * `git branch <name>`
		 * (optional) `git checkout <name>`
		 *
		 * @param string $name
		 * @param bool $checkout
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function createBranch(string $name, bool $checkout = false) : self
		{
			$this->begin();

			// git branch $name
			$this->run('git branch', $name);

			if($checkout)
			{
				$this->checkout($name);
			}

			return $this->end();
		}

		/**
		 * Removes branch.
		 * `git branch -d <name>`
		 *
		 * @param string $name
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function removeBranch(string $name) : self
		{
			return $this->begin()
				->run('git branch', array(
					'-d' => $name,
				))
				->end();
		}

		/**
		 * Gets name of current branch
		 * `git branch` + magic
		 *
		 * @return string|null
		 * @throws GitException
		 */
		public function getCurrentBranchName() : ?string
		{
			try
			{
				$branch = $this->extractFromCommand('git branch -a', function($value) {
					if(isset($value[0]) && $value[0] === '*')
					{
						return trim(substr($value, 1));
					}

					return false;
				});

				if(is_array($branch))
				{
					return $branch[0];
				}
			}
			catch(GitException $e) {}
			throw new GitException('Getting current branch name failed.');
		}

		/**
		 * Returns list of all (local & remote) branches in repo.
		 *
		 * @return string[]|null
		 * @throws GitException
		 */
		public function getBranches() : ?array
		{
			return $this->extractFromCommand('git branch -a', function($value) {
				return trim(substr($value, 1));
			});
		}

		/**
		 * Returns list of remote branches in repo.
		 *
		 * @return string[]|null
		 * @throws GitException
		 */
		public function getRemoteBranches() : ?array
		{
			return $this->extractFromCommand('git branch -r', function($value) {
				return trim(substr($value, 1));
			});
		}

		/**
		 * Returns list of local branches in repo.
		 *
		 * @return string[]|null
		 * @throws GitException
		 */
		public function getLocalBranches() : ?array
		{
			return $this->extractFromCommand('git branch', function($value) {
				return trim(substr($value, 1));
			});
		}

		/**
		 * Checkout branch.
		 * `git checkout <branch>`
		 *
		 * @param string $name
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function checkout(string $name) : self
		{
			return $this->begin()
				->run('git checkout', $name)
				->end();
		}

		/**
		 * Checkout branch.
		 * `git checkout <branch> <filePath>`
		 *
		 * @param string $name
		 * @param string $filePath
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function checkoutFile(string $name, string $filePath) : self
		{
			return $this->begin()
				->run('git checkout', $name, $filePath)
				->end();
		}

		/**
		 * Removes file(s).
		 * `git rm <file>`
		 *
		 * @param string[] $file
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function removeFile(array $file) : self
		{
			$this->begin();

			foreach($file as $item)
			{
				$this->run('git rm', $item, '-r');
			}

			return $this->end();
		}

		/**
		 * Adds files.
		 * `git add <file>`
		 *
		 * @param string[] $file
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function addFiles(array $file) : self
		{
			$this->begin();

			foreach($file as $item)
			{
				// make sure the given item exists
				// this can be a file or an directory, git supports both
				$path = self::isAbsolute($item) ? $item : ($this->getRepositoryPath() . DIRECTORY_SEPARATOR . $item);

				if (!file_exists($path)) {
					throw new GitException("The path at '$item' does not represent a valid file.");
				}

				$this->run('git add -f', $item);
			}

			return $this->end();
		}

		/**
		 * Adds all created, modified & removed files.
		 * `git add --all`
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function addAllChanges() : self
		{
			return $this->begin()
				->run('git add --all')
				->end();
		}

		/**
		 * Renames file(s).
		 * `git mv <file>`
		 *
		 * @param string[]|string $file
		 * @param string|null $to
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function renameFile($file, ?string $to = null) : self
		{
			if(!is_array($file)) // rename(file, to);
			{
				$file = array(
					$file => $to,
				);
			}

			$this->begin();

			foreach($file as $from => $to)
			{
				$this->run('git mv', $from, $to);
			}

			return $this->end();
		}

		/**
		 * Commits changes
		 * `git commit <params> -m <message>`
		 *
		 * @param string $message
		 * @param string[]|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function commit(string $message, ?array $params = null) : self
		{
			if(!is_array($params))
			{
				$params = array();
			}

			return $this->begin()
				->run('git commit', $params, array(
					'-m' => $message,
				))
				->end();
		}

		/**
		 * Returns last commit ID on current branch
		 * `git log --pretty=format:"%H" -n 1`
		 *
		 * @return string|null
		 */
		public function getLastCommitId() : ?string
		{
			$this->begin();
			$lastLine = exec('git log --pretty=format:"%H" -n 1 2>&1');
			$this->end();
			if (preg_match('/^[0-9a-f]{40}$/i', $lastLine)) {
				return $lastLine;
			}
			return null;
		}

		/**
		 * Returns last commit ID on current branch
		 * `git log --pretty=format:"%H" -n 1 filename`
		 *
		 * @var string $filename
		 *
		 * @return string|null
		 */
		public function getLastFileCommitId(string $filename) : ?string
		{
			$this->begin();
			$lastLine = exec('git log --pretty=format:"%H" -n 1 '.$filename.' 2>&1');
			$this->end();
			if (preg_match('/^[0-9a-f]{40}$/i', $lastLine)) {
				return $lastLine;
			}
			return null;
		}

		/**
		 * Exists changes?
		 * `git status` + magic
		 *
		 * @return bool
		 * @throws GitException
		 */
		public function hasChanges() : bool
		{
			// Make sure the `git status` gets a refreshed look at the working tree.
			$this->begin()
				->run('git update-index -q --refresh')
				->end();

			$output = $this->extractFromCommand('git status --porcelain');
			return !empty($output);
		}

		/**
		 * Pull changes from a remote
		 *
		 * @param string|null $remote
		 * @param string[]|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function pull(?string $remote = null, ?array $params = null) : self
		{
			if(!is_array($params))
			{
				$params = array();
			}

			return $this->begin()
				->run('git pull '.$remote, $params)
				->end();
		}

		/**
		 * Push changes to a remote
		 *
		 * @param string|null $remote
		 * @param string[]|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function push(?string $remote = null, ?array $params = null) : self
		{
			if(!is_array($params))
			{
				$params = array();
			}

			return $this->begin()
				->run('git push '.$remote, $params)
				->end();
		}

		/**
		 * Run fetch command to get latest branches
		 *
		 * @param string|null $remote
		 * @param array|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function fetch(?string $remote = null, ?array $params = null) : self
		{
			if(!is_array($params))
			{
				$params = array();
			}

			return $this->begin()
				->run('git fetch '.$remote, $params)
				->end();
		}

		/**
		 * Adds new remote repository
		 *
		 * @param string $name
		 * @param string $url
		 * @param string[]|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function addRemote(string $name, string $url, ?array $params = null) : self
		{
			return $this->begin()
				->run('git remote add', $params, $name, $url)
				->end();
		}

		/**
		 * Renames remote repository
		 *
		 * @param string $oldName
		 * @param string $newName
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function renameRemote(string $oldName, string $newName) : self
		{
			return $this->begin()
				->run('git remote rename', $oldName, $newName)
				->end();
		}

		/**
		 * Removes remote repository
		 *
		 * @param string $name
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function removeRemote(string $name) : self
		{
			return $this->begin()
				->run('git remote remove', $name)
				->end();
		}

		/**
		 * Changes remote repository URL
		 *
		 * @param string $name
		 * @param string $url
		 * @param string[]|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public function setRemoteUrl(string $name, string $url, ?array $params = null) : self
		{
			return $this->begin()
				->run('git remote set-url', $params, $name, $url)
				->end();
		}

		/**
		 * @param string[] $cmd
		 *
		 * @return mixed
		 * @throws GitException
		 */
		public function execute(array $cmd)
		{
			if (!is_array($cmd)) {
				$cmd = array($cmd);
			}

			array_unshift($cmd, 'git');
			$cmd = self::processCommand($cmd);

			$this->begin();
			exec($cmd . ' 2>&1', $output, $ret);
			$this->end();

			if($ret !== 0)
			{
				throw new GitException("Command '$cmd' failed (exit-code $ret).", $ret);
			}

			return $output;
		}


		/**
		 * @return GitRepository
		 */
		protected function begin() : self
		{
			if($this->cwd === null) // TODO: good idea??
			{
				$this->cwd = getcwd();
				chdir($this->repository);
			}

			return $this;
		}


		/**
		 * @return GitRepository
		 */
		protected function end() : self
		{
			if(is_string($this->cwd))
			{
				chdir($this->cwd);
			}

			$this->cwd = null;
			return $this;
		}

		/**
		 * @param string $cmd
		 * @param callable|null $filter
		 *
		 * @return array|null
		 * @throws GitException
		 */
		protected function extractFromCommand(string $cmd, ?callable $filter = null) : ?array
		{
			$output = array();
			$exitCode = null;

			$this->begin();
			exec("$cmd", $output, $exitCode);
			$this->end();

			if($exitCode !== 0 || !is_array($output))
			{
				throw new GitException("Command $cmd failed.");
			}

			if($filter !== null)
			{
				$newArray = array();

				foreach($output as $line)
				{
					$value = $filter($line);

					if($value === false)
					{
						continue;
					}

					$newArray[] = $value;
				}

				$output = $newArray;
			}

			if(!isset($output[0])) // empty array
			{
				return null;
			}

			return $output;
		}

		/**
		 * Runs command.
		 *
		 * @param string|string[] $cmd
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		protected function run($cmd) : self
		{
			$args = func_get_args();
			$cmd = self::processCommand($args);
			exec($cmd . ' 2>&1', $output, $ret);

			if($ret !== 0)
			{
				throw new GitException("Command '$cmd' failed (exit-code $ret).", $ret);
			}

			return $this;
		}


		/**
		 * @param array $args
		 *
		 * @return string
		 */
		protected static function processCommand(array $args) : string
		{
			$cmd = array();

			$programName = array_shift($args);

			foreach($args as $arg)
			{
				if(is_array($arg))
				{
					foreach($arg as $key => $value)
					{
						$_c = '';

						if(is_string($key))
						{
							$_c = "$key ";
						}

						$cmd[] = $_c . escapeshellarg($value);
					}
				}
				elseif(is_scalar($arg) && !is_bool($arg))
				{
					$cmd[] = escapeshellarg($arg);
				}
			}

			return "$programName " . implode(' ', $cmd);
		}

		/**
		 * Init repo in directory
		 *
		 * @param string $directory
		 * @param string[]|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public static function init(string $directory, ?array $params = null) : self
		{
			if(is_dir("$directory/.git"))
			{
				throw new GitException("Repo already exists in $directory.");
			}

			if(!is_dir($directory) && !@mkdir($directory, 0777, true)) // intentionally @; not atomic; from Nette FW
			{
				throw new GitException("Unable to create directory '$directory'.");
			}

			$cwd = getcwd();
			chdir($directory);
			exec(self::processCommand(array(
				'git init',
				$params,
				$directory,
			)), $output, $returnCode);

			if($returnCode !== 0)
			{
				throw new GitException("Git init failed (directory $directory).");
			}

			$repo = getcwd();
			chdir($cwd);

			return new static($repo);
		}

		/**
		 * Clones GIT repository from $url into $directory
		 *
		 * @param string $url
		 * @param string|null $directory
		 * @param string[]|null $params
		 *
		 * @return GitRepository
		 * @throws GitException
		 */
		public static function cloneRepository(string $url, ?string $directory = null, ?array $params = null) : self
		{
			if($directory !== null && is_dir("$directory/.git"))
			{
				throw new GitException("Repo already exists in $directory.");
			}

			$cwd = getcwd();

			if($directory === null)
			{
				$directory = self::extractRepositoryNameFromUrl($url);
				$directory = "$cwd/$directory";
			}
			elseif(!self::isAbsolute($directory))
			{
				$directory = "$cwd/$directory";
			}

			if ($params === null) {
				$params = '-q';
			}

			$descriptorspec = Array(
				0 => Array('pipe', 'r'), // stdout
				1 => Array('pipe', 'w'), // stdin
				2 => Array('pipe', 'w'), // stderr
			);

			$pipes = [];
			$command = self::processCommand(array(
				'git clone',
				$params,
				$url,
				$directory
			));
			$process = proc_open($command, $descriptorspec, $pipes);

			if (!$process)
			{
				throw new GitException("Git clone failed (directory $directory).");
			}

			// Reset output and error
			$stdout = '';
			$stderr = '';

			while (true)
			{
				// Read standard output
				$output = fgets($pipes[0], 1024);

				if ($output)
				{
					$stdout .= $output;
				}

				// Read error output
				$output_err = fgets($pipes[2], 1024);

				if ($output_err)
				{
					$stderr .= $output_err;
				}

				// We are done
				if ((feof($pipes[0]) OR $output === false) AND (feof($pipes[2]) OR $output_err === false))
				{
					break;
				}
			}

			$returnCode = proc_close($process);

			if($returnCode !== 0)
			{
				throw new GitException("Git clone failed (directory $directory)." . ($stderr !== '' ? ("\n$stderr") : ''));
			}

			return new static($directory);
		}

		/**
		 * @param string $url
		 * @param string[]|null $refs
		 *
		 * @return bool
		 */
		public static function isRemoteUrlReadable(string $url, ?array $refs = null) : bool
		{
			if (DIRECTORY_SEPARATOR === '\\') { // Windows
				$env = 'set GIT_TERMINAL_PROMPT=0 &&';
			} else {
				$env = 'GIT_TERMINAL_PROMPT=0';
			}

			exec(self::processCommand(array(
				$env . ' git ls-remote',
				'--heads',
				'--quiet',
				'--exit-code',
				$url,
				$refs,
			)) . ' 2>&1', $output, $returnCode);

			return $returnCode === 0;
		}

		/**
		 * @param string $url /path/to/repo.git | host.xz:foo/.git | ...
		 *
		 * @return string repo | foo | ...
		 */
		public static function extractRepositoryNameFromUrl(string $url) : string
		{
			// /path/to/repo.git => repo
			// host.xz:foo/.git => foo
			$directory = rtrim($url, '/');
			if(substr($directory, -5) === '/.git')
			{
				$directory = substr($directory, 0, -5);
			}

			$directory = basename($directory, '.git');

			if(($pos = strrpos($directory, ':')) !== false)
			{
				$directory = substr($directory, $pos + 1);
			}

			return $directory;
		}

		/**
		 * Is path absolute?
		 * Method from Nette\Utils\FileSystem
		 * @link https://github.com/nette/nette/blob/master/Nette/Utils/FileSystem.php
		 *
		 * @param string $path
		 *
		 * @return bool
		 */
		public static function isAbsolute(string $path) : bool
		{
			return (bool) preg_match('#[/\\\\]|[a-zA-Z]:[/\\\\]|[a-z][a-z0-9+.-]*://#Ai', $path);
		}

		/**
		 * Returns commit message from specific commit
		 * `git log -1 --format={%s|%B} )--pretty=format:'%H' -n 1`
		 *
		 * @param string $commit commit hash
		 * @param bool $oneline use %s instead of %B if true
		 *
		 * @return string
		 */
		public function getCommitMessage(string $commit, bool $oneline = false) : string
		{
			$this->begin();
			exec('git log -1 --format=' . ($oneline ? '%s' : '%B') . ' ' . $commit . ' 2>&1', $message);
			$this->end();
			return implode(PHP_EOL, $message);
		}

		/**
		 * Returns array of commit metadata from specific commit
		 * `git show --raw <sha1>`
		 *
		 * @param string $commit
		 *
		 * @return array
		 */
		public function getCommitData(string $commit) : array
		{
			$message = $this->getCommitMessage($commit);
			$subject = $this->getCommitMessage($commit, true);

			$this->begin();
			exec('git show --raw ' . $commit . ' 2>&1', $output);
			$this->end();
			$data = array(
				'commit' => $commit,
				'subject' => $subject,
				'message' => $message,
				'author' => null,
				'committer' => null,
				'date' => null,
			);

			// git show is a porcelain command and output format may changes
			// in future git release or custom config.
			foreach ($output as $index => $info) {
				if (preg_match('`Author: *(.*)`', $info, $author)) {
					$data['author'] = trim($author[1]);
					unset($output[$index]);
				}
				if (preg_match('`Commit: *(.*)`', $info, $committer)) {
					$data['committer'] = trim($committer[1]);
					unset($output[$index]);
				}
				if (preg_match('`Date: *(.*)`', $info, $date)) {
					$data['date'] = trim($date[1]);
					unset($output[$index]);
				}
			}
			return $data;
		}
	}
