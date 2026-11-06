<?php
$filterParams=['q'=>$q,'category'=>$category,'min_reward'=>$min,'sort'=>$sort];
$firstResult=$totalTasks>0?(($page-1)*$perPage)+1:0;
$lastResult=min($page*$perPage,$totalTasks);
$pageLink=fn(int $target)=>url('browse',$filterParams+['page'=>$target]);
$activeFilters=[];
if($q!=='')$activeFilters[]=['Search: '.$q,url('browse',['category'=>$category,'min_reward'=>$min,'sort'=>$sort])];
if($category>0){foreach($categories as $item){if((int)$item['id']===$category){$activeFilters[]=['Category: '.$item['name'],url('browse',['q'=>$q,'min_reward'=>$min,'sort'=>$sort])];break;}}}
if($min>0)$activeFilters[]=['Reward: ৳'.number_format($min,0).'+',url('browse',['q'=>$q,'category'=>$category,'sort'=>$sort])];
?>
<section class="view active browse-page">
  <div class="container page-top">
    <span class="eyebrow">Task marketplace</span>
    <h1>Find your next microtask</h1>
    <p>Compare reward, effort, deadline and available worker slots before claiming.</p>
  </div>
  <div class="container browse-content">
    <form class="marketplace-filters" method="get" action="index.php">
      <input type="hidden" name="route" value="browse">
      <label class="marketplace-search"><i data-lucide="search"></i><input type="search" name="q" value="<?=e($q)?>" placeholder="Search task title or keyword" aria-label="Search tasks"><button class="clear-search" type="reset" aria-label="Clear search"><i data-lucide="x"></i></button></label>
      <label class="filter-control"><span>Category</span><select name="category"><option value="0">All categories</option><?php foreach($categories as $cat): ?><option value="<?=$cat['id']?>" <?=$category===(int)$cat['id']?'selected':''?>><?=e($cat['name'])?></option><?php endforeach; ?></select></label>
      <label class="filter-control"><span>Minimum reward</span><select name="min_reward"><option value="0">Any reward</option><option value="25" <?=$min==25?'selected':''?>>৳25+</option><option value="50" <?=$min==50?'selected':''?>>৳50+</option><option value="100" <?=$min==100?'selected':''?>>৳100+</option><option value="250" <?=$min==250?'selected':''?>>৳250+</option></select></label>
      <label class="filter-control"><span>Sort by</span><select name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Newest first</option><option value="reward" <?=$sort==='reward'?'selected':''?>>Highest reward</option><option value="slots" <?=$sort==='slots'?'selected':''?>>Most available slots</option><option value="deadline" <?=$sort==='deadline'?'selected':''?>>Closest deadline</option></select></label>
      <button class="btn marketplace-apply" type="submit"><i data-lucide="sliders-horizontal"></i> Apply filters</button>
    </form>

    <div class="browse-result-bar">
      <div><strong><?=number_format($totalTasks)?></strong> <?=($totalTasks===1?'task':'tasks')?> available <span class="result-range">· Showing <?=number_format($firstResult)?>–<?=number_format($lastResult)?></span></div>
      <a class="browse-reset" href="<?=e(url('browse'))?>"><i data-lucide="rotate-ccw"></i> Reset filters</a>
    </div>
    <?php if($activeFilters): ?><div class="active-filter-list" aria-label="Active filters"><?php foreach($activeFilters as [$label,$removeUrl]): ?><a class="active-filter" href="<?=e($removeUrl)?>"><?=e($label)?><i data-lucide="x"></i></a><?php endforeach; ?></div><?php endif; ?>

    <div class="task-grid marketplace-task-grid">
      <?php if($tasks): foreach($tasks as $task)echo task_card($task); else: ?>
        <div class="task-empty"><i data-lucide="search-x"></i><h3>No matching tasks</h3><p>Try another keyword, reward range or category. Approved campaigns appear here when slots are available.</p><a class="btn sm secondary" href="<?=e(url('browse'))?>">Clear all filters</a></div>
      <?php endif; ?>
    </div>

    <?php if($totalPages>1): ?><nav class="marketplace-pagination" aria-label="Task result pages">
      <?php if($page>1): ?><a class="pagination-link" href="<?=e($pageLink($page-1))?>" rel="prev"><i data-lucide="chevron-left"></i> Previous</a><?php else: ?><span class="pagination-link is-disabled"><i data-lucide="chevron-left"></i> Previous</span><?php endif; ?>
      <div class="pagination-pages"><?php
        $startPage=max(1,$page-2);$endPage=min($totalPages,$page+2);
        if($startPage>1){echo '<a class="pagination-number" href="'.e($pageLink(1)).'">1</a>';if($startPage>2)echo '<span class="pagination-ellipsis">…</span>';}
        for($n=$startPage;$n<=$endPage;$n++){if($n===$page)echo '<span class="pagination-number is-current" aria-current="page">'.$n.'</span>';else echo '<a class="pagination-number" href="'.e($pageLink($n)).'">'.$n.'</a>';}
        if($endPage<$totalPages){if($endPage<$totalPages-1)echo '<span class="pagination-ellipsis">…</span>';echo '<a class="pagination-number" href="'.e($pageLink($totalPages)).'">'.$totalPages.'</a>';}
      ?></div>
      <?php if($page<$totalPages): ?><a class="pagination-link" href="<?=e($pageLink($page+1))?>" rel="next">Next <i data-lucide="chevron-right"></i></a><?php else: ?><span class="pagination-link is-disabled">Next <i data-lucide="chevron-right"></i></span><?php endif; ?>
    </nav><?php endif; ?>
  </div>
</section>
